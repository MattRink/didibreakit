<?php

namespace MattRink\didibreakit;

use Doctrine\Common\Collections\ArrayCollection;

final class Host extends ArrayCollection
{
    private $options;
    private $scheme;
    private $hostname;

    public function __construct(Options $options, string $scheme, string $hostname, array $urls)
    {
        $this->options = $options;
        $this->scheme = $scheme;
        $this->hostname = $hostname;

        // @TODO Validate urls have a status code here.

        $defaultURLs = $options->getDefaultURLs();
        $urls = array_merge($defaultURLs, $urls);

        parent::__construct($urls);
    }

    public function getOptions() : Options
    {
        return $this->options;
    }

    public function getScheme() : string
    {
        return $this->scheme ? $this->scheme : $this->options->getDefaultScheme();
    }

    public function getHostname()
    {
        $hostname = $this->options->getUrlPattern();
        $hostname = str_replace('%host%', $this->hostname, $hostname);

        if ($this->options->hasBranch()) {
            $hostname = str_replace('%branch%', $this->options->getBranch(), $hostname);
        }

        return $hostname;
    }

    protected function createFrom(array $urls) : self
    {
        return new static($this->options, $this->scheme, $this->hostname, $urls);
    }
}