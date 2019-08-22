<?php

namespace MattRink\didibreakit;

use InvalidArgumentException;

final class Options
{
    private $urlPattern = '';
    private $defaultScheme = 'https';
    private $branch = '';
    private $verifySSL = true;
    private $defaultURLs = [];

    /**
     * Left empty to prevent instantiating it directly.
     */
    private function __construct()
    {

    }

    public static function loadFromArray(array $configOptions, string $branch = '')
    {
        $options = new Options();

        if (!isset($configOptions['urlPattern'])) {
            throw new InvalidArgumentException("Config file is missing the required urlPattern parameter.");
        }

        $options->urlPattern = $configOptions['urlPattern'];
        $options->defaultScheme = $configOptions['defaultScheme'] ?? 'https';
        $options->branch = $branch;
        $options->verifySSL = $configOptions['verifySSL'] ?? true;
        $options->defaultURLs = $configOptions['defaultURLs'] ?? [];

        return $options;
    }

    public function getUrlPattern() : string
    {
        return $this->urlPattern;
    }

    public function getDefaultScheme() : string
    {
        return $this->defaultScheme;
    }

    public function hasBranch() : bool
    {
        return !empty($this->branch);
    }

    public function getBranch() : string
    {
        return $this->branch;
    }

    public function verifySSL() : bool
    {
        return $this->verifySSL;
    }

    public function getDefaultURLs() : array
    {
        return $this->defaultURLs;
    }
}