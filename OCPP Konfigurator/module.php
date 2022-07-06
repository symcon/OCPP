<?php

declare(strict_types=1);
    class OCPPKonfigurator extends IPSModule
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
            $bufferList = $this->GetBufferList();
            if (!in_array($messageID, $bufferList)) {
                $this->SetBuffer($messageID, json_encode($jsonArray['Buffer'][3]));
            } else {
                $buffer = $this->GetBuffer($messageID);
                if ($buffer != json_encode($jsonArray['Buffer'][3])) {
                    $this->SetBuffer($messageID, json_encode($jsonArray['Buffer'][3]));
                }
            }
            return $this->InstanceID;
        }

        public function GetConfigurationForm()
        {
            $availablePoints = [];
            //$this->SendDebug("Input", print_r($this->Buffer),0);

            //Get the points who send BootMessages
            $bufferList = $this->GetBufferList();
            foreach ($bufferList as $messageID) {
                $buffer = $this->GetBuffer($messageID);
                $message = json_decode($buffer, true);
                $availablePoints[] = [
                    'vendor'       => $message['chargePointVendor'],
                    'model'        => $message['chargePointModel'],
                    'serialNumber' => $message['chargePointSerialNumber'],
                    'messageID'    => $messageID,
                    'create'       => [
                        'moduleID'      => '{2EDDBD05-F295-3A79-00BD-B2FC0F107134}',
                        'configuration' => [
                            'vendor'       => $message['chargePointVendor'],
                            'model'        => $message['chargePointModel'],
                            'serialNumber' => $message['chargePointSerialNumber'],
                            'messageID'    => $messageID,
                        ]
                    ]
                ];
            }

            //Get the Instance and set the right ids or add it to the list
            foreach (IPS_GetInstanceListByModuleID('{2EDDBD05-F295-3A79-00BD-B2FC0F107134}') as $instanceID) {
                if (in_array(IPS_GetProperty($instanceID, 'messageID'), $bufferList)) {
                    $key = array_search(array_column($availablePoints, 'instanceID'), $availablePoints);
                    $availablePoints[$key]['instanceID'] = $instanceID;
                } else {
                    $availablePoints[] = [
                        'vendor'       => IPS_GetProperty($instanceID, 'vendor'),
                        'model'        => IPS_GetProperty($instanceID, 'model'),
                        'serialNumber' => IPS_GetProperty($instanceID, 'serialNumber'),
                        'messageID'    => IPS_GetProperty($instanceID, 'messageID'),
                        'instanceID'   => $instanceID,
                    ];
                }
            }

            return json_encode([
                'actions' => [
                    [
                        'type'    => 'Configurator',
                        'caption' => $this->Translate('Charging Points'),
                        'columns' => [
                            [
                                'name'    => 'vendor',
                                'caption' => 'Vendor',
                                'width'   => 'auto'
                            ],
                            [
                                'name'    => 'model',
                                'caption' => 'Model',
                                'width'   => '200px',
                            ],
                            [
                                'name'    => 'serialNumber',
                                'caption' => 'Serial Number',
                                'width'   => '200px'
                            ],
                            [
                                'name'    => 'messageID',
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
