<?php

namespace EricMakesStuff\ServerMonitor\Monitors;

use EricMakesStuff\ServerMonitor\Events\HttpPingDown;
use EricMakesStuff\ServerMonitor\Events\HttpPingUp;
use EricMakesStuff\ServerMonitor\Exceptions\InvalidConfiguration;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class HttpPingMonitor extends BaseMonitor
{
    /**  @var int */
    protected $responseCode;

    /**  @var string */
    protected $responseContent;

    /**  @var bool */
    protected $responseContainsPhrase = false;

    /**  @var string */
    protected $url;

    /**  @var bool|string */
    protected $checkPhrase = false;

    /** @var int */
    protected $timeout = 5;

    /** @var bool */
    protected $allowRedirects = true;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!empty($config['url'])) {
            $this->url = $config['url'];
        }

        if (!empty($config['checkPhrase'])) {
            $this->checkPhrase = $config['checkPhrase'];
        }

        if (!empty($config['timeout'])) {
            $this->timeout = $config['timeout'];
        }

        if (!empty($config['allowRedirects'])) {
            $this->allowRedirects = $config['allowRedirects'];
        }
    }

    /**
     * @throws InvalidConfiguration
     */
    public function runMonitor()
    {
        if (empty($this->url)) {
            throw InvalidConfiguration::noUrlConfigured();
        }

        $this->responseCode = null;

        try {
            $guzzle = new Guzzle([
                'timeout' => $this->timeout,
                'allow_redirects' => $this->allowRedirects,
            ]);
            $response = $guzzle->get($this->url);
            $this->responseCode = $response->getStatusCode();
            $this->responseContent = (string)$response->getBody();
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response instanceof ResponseInterface) {
                $this->responseCode = $response->getStatusCode();
                $this->responseContent = (string)$response->getBody();
            } else {
                $this->responseContent = $e->getMessage();
            }

        } catch (\Exception $e) {
            $this->responseContent = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
        }

        if ($this->checkPhrase) {
            if ($this->checkResponseContains($this->responseContent, $this->checkPhrase)) {
                event(new HttpPingUp($this));
            } else {
                event(new HttpPingDown($this));
            }
        } else {
            if ($this->responseCode == '200') {
                event(new HttpPingUp($this));
            } else {
                event(new HttpPingDown($this));
            }
        }
    }

    protected function checkResponseContains($html, $phrase)
    {
        $this->responseContainsPhrase = str_contains($html, $phrase);

        return $this->responseContainsPhrase;
    }

    public function getResponseContainsPhrase()
    {
        return $this->responseContainsPhrase;
    }

    public function getCheckPhrase()
    {
        return $this->checkPhrase;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    public function getResponseContent()
    {
        return $this->responseContent;
    }

    public function getUrl()
    {
        return $this->url;
    }
}
