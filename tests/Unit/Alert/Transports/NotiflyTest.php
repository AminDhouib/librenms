<?php

/**
 * NotiflyTest.php
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
 * @link       https://www.librenms.org
 *
 * @copyright  2026 Notifly
 */

namespace LibreNMS\Tests\Unit\Alert\Transports;

use App\Models\AlertTransport;
use App\Models\Device;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LibreNMS\Alert\AlertData;
use LibreNMS\Alert\Transport;
use LibreNMS\Enum\AlertState;
use LibreNMS\Exceptions\AlertTransportDeliveryException;
use LibreNMS\Tests\TestCase;

final class NotiflyTest extends TestCase
{
    public function testAlertDelivery(): void
    {
        Http::fake([
            'api.notifly.io/*' => Http::response([
                'data' => ['acknowledged' => true, 'status' => 'processed'],
            ], 201),
        ]);

        /** @var Device $mock_device */
        $mock_device = Device::factory()->make();
        $alert_data = AlertData::testData($mock_device);

        $this->transport()->deliverAlert($alert_data);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.notifly.io/v1/events/trigger'
            && $request->method() === 'POST'
            && $request->header('Authorization') === ['ApiKey test-api-key']
            && $request->data() === [
                'name' => 'test-workflow',
                'to' => ['subscriberId' => 'test-subscriber'],
                'payload' => [
                    'title' => 'Testing transport from LibreNMS',
                    'body' => 'This is a test alert',
                    'severity' => 'critical',
                    'state' => 'alert',
                    'hostname' => $mock_device->hostname,
                ],
            ]);
    }

    public function testRecoveredDelivery(): void
    {
        Http::fake([
            'api.notifly.io/*' => Http::response([
                'data' => ['acknowledged' => true, 'status' => 'processed'],
            ], 201),
        ]);

        /** @var Device $mock_device */
        $mock_device = Device::factory()->make();
        $alert_data = AlertData::testData($mock_device);
        $alert_data['state'] = AlertState::RECOVERED;

        $this->transport()->deliverAlert($alert_data);

        Http::assertSent(fn (Request $request) => $request->data()['payload']['state'] === 'recovered');
    }

    public function testWorkflowNotProcessed(): void
    {
        Http::fake([
            'api.notifly.io/*' => Http::response([
                'data' => ['acknowledged' => true, 'status' => 'trigger_not_active'],
            ], 201),
        ]);

        $this->expectException(AlertTransportDeliveryException::class);

        /** @var Device $mock_device */
        $mock_device = Device::factory()->make();

        $this->transport()->deliverAlert(AlertData::testData($mock_device));
    }

    public function testApiErrorDelivery(): void
    {
        Http::fake([
            'api.notifly.io/*' => Http::response([
                'statusCode' => 401,
                'message' => 'Invalid API key',
            ], 401),
        ]);

        $this->expectException(AlertTransportDeliveryException::class);

        /** @var Device $mock_device */
        $mock_device = Device::factory()->make();

        $this->transport()->deliverAlert(AlertData::testData($mock_device));
    }

    public function testApiErrorWithListMessage(): void
    {
        Http::fake([
            'api.notifly.io/*' => Http::response([
                'statusCode' => 400,
                'message' => ['name must be a string', 'to must be an object'],
            ], 400),
        ]);

        $this->expectException(AlertTransportDeliveryException::class);

        /** @var Device $mock_device */
        $mock_device = Device::factory()->make();

        $this->transport()->deliverAlert(AlertData::testData($mock_device));
    }

    private function transport(): Transport\Notifly
    {
        return new Transport\Notifly(new AlertTransport([
            'transport_config' => [
                'notifly-api-key' => 'test-api-key',
                'notifly-workflow' => 'test-workflow',
                'notifly-subscriber' => 'test-subscriber',
            ],
        ]));
    }
}
