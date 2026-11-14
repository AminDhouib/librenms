<?php

/**
 * LibreNMS Notifly alerting transport
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @link        https://www.librenms.org
 *
 * @copyright   2026 Notifly
 * @license     GPL
 */

namespace LibreNMS\Alert\Transport;

use LibreNMS\Alert\Transport;
use LibreNMS\Enum\AlertState;
use LibreNMS\Exceptions\AlertTransportDeliveryException;
use LibreNMS\Util\Http;

class Notifly extends Transport
{
    /**
     * @param  array<string, mixed>  $alert_data
     */
    public function deliverAlert(array $alert_data): bool
    {
        $state = match ($alert_data['state']) {
            AlertState::RECOVERED => 'recovered',
            AlertState::ACKNOWLEDGED => 'acknowledged',
            AlertState::WORSE => 'worse',
            AlertState::BETTER => 'better',
            default => 'alert',
        };

        $data = [
            'name' => $this->config['notifly-workflow'],
            'to' => ['subscriberId' => $this->config['notifly-subscriber']],
            'payload' => [
                'title' => $alert_data['title'],
                'body' => $alert_data['msg'],
                'severity' => $alert_data['severity'],
                'state' => $state,
                'hostname' => $alert_data['hostname'],
            ],
        ];

        $res = Http::client()
            ->withHeaders(['Authorization' => 'ApiKey ' . $this->config['notifly-api-key']])
            ->acceptJson()
            ->post('https://api.notifly.io/v1/events/trigger', $data);

        $response = $res->json('data');

        if ($res->successful() && ($response['acknowledged'] ?? false) === true && ($response['status'] ?? null) === 'processed') {
            return true;
        }

        // validation errors can return a list or an object as the message
        $error = $res->json('message');

        throw new AlertTransportDeliveryException(
            $alert_data,
            $res->status(),
            is_string($error) ? $error : ($response['status'] ?? $res->body()),
            $alert_data['msg'],
            $data
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function configTemplate(): array
    {
        return [
            'config' => [
                [
                    'title' => 'API Key',
                    'name' => 'notifly-api-key',
                    'descr' => 'Notifly API Key',
                    'type' => 'password',
                ],
                [
                    'title' => 'Workflow',
                    'name' => 'notifly-workflow',
                    'descr' => 'Notifly workflow trigger identifier',
                    'type' => 'text',
                ],
                [
                    'title' => 'Subscriber ID',
                    'name' => 'notifly-subscriber',
                    'descr' => 'Notifly subscriber ID to notify',
                    'type' => 'text',
                ],
            ],
            'validation' => [
                'notifly-api-key' => 'required|string',
                'notifly-workflow' => 'required|string',
                'notifly-subscriber' => 'required|string',
            ],
        ];
    }
}
