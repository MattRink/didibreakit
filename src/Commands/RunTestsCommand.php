<?php

namespace MattRink\didibreakit\Commands;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MattRink\didibreakit\ConfigLoader;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'The test branch to target.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $returnCode = 0;
        $configFile = $input->getArgument('config-file');
        $branch = $input->getOption('branch');
        $verbose = $input->getOption('verbose');
        $hosts = ConfigLoader::load($configFile, $branch);

        $handler = \GuzzleHttp\HandlerStack::create();
        $requests = [];

        // Build the requests
        /** @var Host $host */
        foreach ($hosts as $host) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => $host->getOptions()->verifySSL(),
                'timeout' => $host->getOptions()->getTimeout(),
                'handler' => $handler,
            ]);

            foreach ($host as $url => $expectedStatusCode) {
                $uri = \GuzzleHttp\Psr7\Uri::composeComponents($host->getScheme(), $host->getHostName(), $url, '', '');
                $request = new Request('GET', $uri);
                $requests[] = [
                    'host' => $host,
                    'client' => $httpClient,
                    'request' => $request,
                    'uri' => $uri,
                    'expectedStatusCode' => $expectedStatusCode
                ];
            }
        }

        // Set up a generator to send the requests
        $promises = (function() use ($output, $requests, $verbose) {
            foreach ($requests as $requestIndex => $request) {
                $httpClient = $request['client'];
                $uri = $request['uri'];
                $expectedStatusCode = $request['expectedStatusCode'];

                if ($verbose) {
                    $output->writeln("<info>[{$requestIndex}] Sending request to {$uri} expecting {$expectedStatusCode} response.</info>");
                }

                yield $httpClient->sendAsync($request['request']);
            }
        })();

        // Function to handle responses
        $handleResponse = function ($response, int $requestIndex) use ($output, $verbose, $requests, &$returnCode) {
            $message = '';
            $statusCode = 0;
            $timeout = false;

            $expectedStatusCode = $requests[$requestIndex]['expectedStatusCode'];
            $uri = $requests[$requestIndex]['uri'];
            $options = $requests[$requestIndex]['host']->getOptions();

            $responseClass = get_class($response);

            if ($verbose) {
                $output->writeln("<info>[{$requestIndex}] Received {$responseClass} response for {$uri}.</info>");
            }

            switch ($responseClass) {
                case ConnectException::class:
                    $timeout = $options->failOnTimeout();
                case ClientException::class;
                case RequestException::class:
                    $statusCode = $response->getCode();
                    $message = $response->getMessage();
                    break;
                case Response::class:
                    $statusCode = $response->getStatusCode();
                    break;
            }

            if ($timeout || $expectedStatusCode != $statusCode) {
                if ($verbose && !empty($message)) {
                    $output->writeln("<error>[{$requestIndex}] {$message}</error>");
                }
                $output->writeln("<error>[{$requestIndex}] {$uri} failed. Received status code {$statusCode} but expected {$expectedStatusCode}.</error>");
                $returnCode = 1;
                return;
            }

            $output->writeln("[{$requestIndex}] {$uri}");
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