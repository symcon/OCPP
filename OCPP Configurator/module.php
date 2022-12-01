<?php

declare(strict_types=1);

class OCPPConfigurator extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->SetReceiveDataFilter('.*BootNotification.*');

        $this->RequireParent('{D048F0F0-0015-E50B-E8EC-731998FDFDA8}');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function ReceiveData($JSON)
    {
        $data = json_decode($JSON, true);

        //store the received BootNotification payload in a Buffer
        $this->SetBuffer($data['ChargePointIdentity'], json_encode($data['Message'][3]));

        return $this->InstanceID;
    }

    public function GetConfigurationForm()
    {
        /**
         * Payload of BootMessage
         * OCPP-j-1.6-edition 2.pdf Page 65 BootNotification.req
         */
        $availableChargePoints = [];

        //Get the points who send BootMessages
        $bufferList = $this->GetBufferList();
        foreach ($bufferList as $chargePointIdentity) {
            $buffer = $this->GetBuffer($chargePointIdentity);
            $payload = json_decode($buffer, true);
            $availableChargePoints[] = [
                'Vendor'              => $payload['chargePointVendor'],
                'Model'               => $payload['chargePointModel'],
                'SerialNumber'        => $payload['chargePointSerialNumber'],
                'ChargePointIdentity' => $chargePointIdentity,
                'create'              => [
                    'moduleID'      => '{2EDDBD05-F295-3A79-00BD-B2FC0F107134}',
                    'configuration' => [
                        'ChargePointIdentity' => $chargePointIdentity,
                    ]
                ]
            ];
        }

        //Get the Instance and set the right ids or add it to the list
        foreach (IPS_GetInstanceListByModuleID('{2EDDBD05-F295-3A79-00BD-B2FC0F107134}') as $instanceID) {
            $found = false;
            foreach($availableChargePoints as $index => $availableChargePoint) {
                if ($availableChargePoint['ChargePointIdentity'] == IPS_GetProperty($instanceID, 'ChargePointIdentity')) {
                    $availableChargePoints[$index]['instanceID'] = $instanceID;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $availableChargePoints[] = [
                    'Vendor'       => GetValue(IPS_GetObjectIDByIdent('Vendor', $instanceID)),
                    'Model'        => GetValue(IPS_GetObjectIDByIdent('Model', $instanceID)),
                    'SerialNumber' => GetValue(IPS_GetObjectIDByIdent('SerialNumber', $instanceID)),
                    'ChargePointIdentity'    => IPS_GetProperty($instanceID, 'ChargePointIdentity'),
                    'InstanceID'   => $instanceID,
                ];
            }
        }

        return json_encode([
            'actions' => [
                [
                    'type'    => 'Configurator',
                    'caption' => 'Charging Points',
                    'columns' => [
                        [
                            'name'    => 'Vendor',
                            'caption' => 'Vendor',
                            'width'   => 'auto'
                        ],
                        [
                            'name'    => 'Model',
                            'caption' => 'Model',
                            'width'   => '200px',
                        ],
                        [
                            'name'    => 'SerialNumber',
                            'caption' => 'Serial Number',
                            'width'   => '200px'
                        ],
                        [
                            'name'    => 'ChargePointIdentity',
                            'caption' => 'Charge Point Identity',
                            'width'   => '200px'
                        ]

                    ],
                    'values' => $availableChargePoints
                ]
            ]
        ]);
    }
}
