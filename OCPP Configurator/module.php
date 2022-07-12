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
        //store the recive data in a Buffer
        $jsonArray = json_decode($JSON, true);
        $messageID = $jsonArray['Buffer'][1];
        $payload = json_encode($jsonArray['Buffer'][3]);

        $this->SetBuffer($messageID, $payload);

        return $this->InstanceID;
    }

    public function GetConfigurationForm()
    {   
        /**
         * Payload of BootMessage
         * OCPP-j-1.6-edition 2.pdf Page 65 BootNotification.req
         */

        $availablePoints = [];

        //Get the points who send BootMessages
        $bufferList = $this->GetBufferList();
        foreach ($bufferList as $messageID) {
            $buffer = $this->GetBuffer($messageID);
            $message = json_decode($buffer, true);
            $availablePoints[] = [
                'Vendor'       => $message['chargePointVendor'],
                'Model'        => $message['chargePointModel'],
                'SerialNumber' => $message['chargePointSerialNumber'],
                'MessageID'    => $messageID,
                'create'       => [
                    'moduleID'      => '{2EDDBD05-F295-3A79-00BD-B2FC0F107134}',
                    'configuration' => [
                        'Vendor'       => $message['chargePointVendor'],
                        'Model'        => $message['chargePointModel'],
                        'SerialNumber' => $message['chargePointSerialNumber'],
                        'MessageID'    => $messageID,
                    ]
                ]
            ];
        }

        //Get the Instance and set the right ids or add it to the list
        foreach (IPS_GetInstanceListByModuleID('{2EDDBD05-F295-3A79-00BD-B2FC0F107134}') as $instanceID) {
            if (in_array(IPS_GetProperty($instanceID, 'MessageID'), $bufferList)) {
                $key = array_search(array_column($availablePoints, 'instanceID'), $availablePoints);
                $availablePoints[$key]['instanceID'] = $instanceID;
            } else {
                $availablePoints[] = [
                    'Vendor'       => IPS_GetProperty($instanceID, 'Vendor'),
                    'Model'        => IPS_GetProperty($instanceID, 'Model'),
                    'SerialNumber' => IPS_GetProperty($instanceID, 'SerialNumber'),
                    'MessageID'    => IPS_GetProperty($instanceID, 'MessageID'),
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
                            'name'    => 'MessageID',
                            'caption' => 'MessageID',
                            'width'   => '200px'
                        ]

                    ],
                    'values' => $availablePoints
                ]
            ]
        ]);
    }
}
