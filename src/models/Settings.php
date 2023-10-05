<?php
/**
 * Oh Dear plugin for Craft CMS 4.x
 *
 * Integrate Oh Dear into Craft CMS.
 *
 * @link      https://webhub.de
 * @copyright Copyright (c) 2019 webhub GmbH
 */

namespace webhubworks\ohdear\models;

use Craft;
use craft\base\Model;
use OhDear\PhpSdk\OhDear as OhDearSdk;
use OhDear\PhpSdk\Resources\Site;
use webhubworks\ohdear\OhDear;
use OhDear\PhpSdk\Exceptions\UnauthorizedException;
use OhDear\PhpSdk\Resources\User as OhDearUser;
use OhDear\PhpSdk\Resources\Site as OhDearSite;

/**
 * @author    webhub GmbH
 * @package   OhDear
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    public string $apiToken = '';

    public string $selectedSiteId = '';

    public ?string $healthCheckSecret = null;

    public array $healthChecks = [];

    /**
     * Determines if the plugin has an API key and
     * a selected site ID.
     */
    public function isValid(): bool
    {
        return ! empty($this->apiToken) && ! empty($this->selectedSiteId);
    }

    /**
     * Parse the site ID if it is an env variable, otherwise
     * just return the value.
     */
    public function getSelectedSiteId(): string
    {
        return Craft::parseEnv($this->selectedSiteId);
    }

    /**
     * Parse the API token if it is an env variable, otherwise
     * just return the value.
     */
    public function getApiToken(): string
    {
        return Craft::parseEnv($this->apiToken);
    }

    public function getSite(): ?Site
    {
        if (! $this->isValid()) {
            return null;
        }

        return OhDear::$plugin->api->getSite();
    }

    public function getHealthReportUrl(string $healthReportUri): ?string
    {
        try {
            $site = $this->getSite();
            if ($site instanceof Site) {
                return implode("/", [$site->url, $healthReportUri]);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function rules(): array
    {
        return [
            [['apiToken', 'selectedSiteId'], 'trim'],
            [['apiToken', 'selectedSiteId'], 'default', 'value' => ''],
            ['selectedSiteId', 'required', 'when' => function ($model) {
                return ! empty($model->apiToken);
            }],
            [['apiToken'], 'validApiToken'],
            [['selectedSiteId'], 'validSelectedSiteId'],
        ];
    }

    public function validApiToken($attribute, $params): void
    {
        try {
            $user = (new OhDearSdk($this[$attribute]))->me();
            if (! ($user instanceof OhDearUser)) {
                throw new \Exception('Invalid API response. Please contact support.');
            }
        } catch (UnauthorizedException $e) {
            $this->addError('apiToken', 'API authentication failed.');
        } catch (\Exception $e) {
            $this->addError('apiToken', $e->getMessage());
        }
    }

    public function validSelectedSiteId($attribute, $params): void
    {
        if (! $this->isValid()) {
            return;
        }

        try {
            $site = (new OhDearSdk($this->apiToken))->site($this[$attribute]);
            if (! ($site instanceof OhDearSite)) {
                throw new \Exception('Invalid API response. Please contact support.');
            }
        } catch (UnauthorizedException $e) {
            $this->addError('selectedSiteId', 'API authentication failed.');
        } catch (\Exception $e) {
            $this->addError('selectedSiteId', $e->getMessage());
        }
    }
}
