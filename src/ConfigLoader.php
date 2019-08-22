<?php

namespace MattRink\didibreakit;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

abstract class ConfigLoader
{
    private function __construct()
    {

    }

    public static function load(string $configFile, string $branch) : HostsCollection
    {
        $config = Yaml::parseFile($configFile);
        $options = Options::loadFromArray($config['options'], $branch);

        if (empty($config['hosts'])) {
            throw new InvalidArgumentException('Not hosts defined in the provided configuration.');
        }

        $hostsConfig = $config['hosts'];
        $hosts = new HostsCollection();

        foreach ($hostsConfig as $hostname => $hostConfig) {
            $hostUrls = $hostConfig['urls'] ?? [];
            $hostScheme = $hostConfig['scheme'] ?? $options->getDefaultScheme();
            $host = new Host($options, $hostScheme, $hostname, $hostUrls);
            $hosts->add($host);
        }

        return $hosts;
    }
}