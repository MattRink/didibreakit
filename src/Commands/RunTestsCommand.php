<?php

namespace MattRink\didibreakit\Commands;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MattRink\didibreakit\ConfigLoader;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RunTestsCommand extends Command
{
    protected static $defaultName = 'tests:run';

    protected function configure()
    {
        $this
            ->setDescription('Executes all tests defined in the specified yaml config.')
            ->addArgument('config-file', InputArgument::REQUIRED, 'The config file for this set of tests.')
            ->addOption('branch', 'b', InputArgument::OPTIONAL, 'The test branch to target.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $returnCode = 0;
        $configFile = $input->getArgument('config-file');
        $branch = $input->getOption('branch');
        $hosts = ConfigLoader::load($configFile, $branch);

        $handler = \GuzzleHttp\HandlerStack::create();
        $requests = [];

        // Build the requests
        /** @var Host $host */
        foreach ($hosts as $host) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => $host->getOptions()->verifySSL(),
                'handler' => $handler,
            ]);

            foreach ($host as $url => $expectedStatusCode) {
                $uri = \GuzzleHttp\Psr7\Uri::composeComponents($host->getScheme(), $host->getHostName(), $url, '', '');
                $request = new Request('GET', $uri);
                $requests[] = [
                    'client' => $httpClient,
                    'request' => $request,
                    'uri' => $uri,
                    'expectedStatusCode' => $expectedStatusCode
                ];
            }
        }

        // Set up a generator to send the requests
        $promises = (function() use ($requests) {
            foreach ($requests as $request) {
                $httpClient = $request['client'];
                yield $httpClient->sendAsync($request['request']);
            }
        })();

        // Function to handle responses
        $handleResponse = function ($response, int $requestIndex) use ($output, $requests, &$returnCode) {
            if ($response instanceof ClientException) {
                $statusCode = $response->getCode();
            } else {
                $statusCode = $response->getStatusCode();
            }

            $expectedStatusCode = $requests[$requestIndex]['expectedStatusCode'];
            $uri = $requests[$requestIndex]['uri'];

            if ($expectedStatusCode != $statusCode) {
                $output->writeln("<error>{$uri} failed. Received {$statusCode} expected {$expectedStatusCode}.</error>");
                $returnCode = 1;
                return;
            }

            $output->writeln("<info>{$uri}</info>");
        };

        // Create a pool/promise to handle the requests
        $pool = new EachPromise($promises, [
            'concurrency' => 8,
            'fulfilled' => $handleResponse,
            'rejected' => $handleResponse,
        ]);

        // Run it...
        $promise = $pool->promise();
        // And wait...
        $promise->wait();

        return $returnCode;
    }
}