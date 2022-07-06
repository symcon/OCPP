<?php

declare(strict_types=1);
    class OCPPChargingPoint extends IPSModule
    {
        public function Create()
        {
            //Never delete this line!
            parent::Create();

            //Properties
            $this->RegisterPropertyString('vendor', '');
            $this->RegisterPropertyString('model', '');
            $this->RegisterPropertyString('serialNumber', '');
            $this->RegisterPropertyString('messageID', '');

            //Variables
            $this->RegisterVariableFloat('meterValue', $this->Translate('Meter Value'), '~Electricity.Wh');
            $this->RegisterVariableBoolean('transaction', $this->Translate('Transaction run'));
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

            $this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('messageID') . '.*');
        }

        public function ReceiveData($JSONString)
        {
            $data = json_decode($JSONString);
            //$this->SendDebug('DATA', $JSONString, 0);
            $messageType = $data->Buffer[2];
            $payload = $data->Buffer[3];
            //$this->SendDebug('messageType', $messageType, 0);
            switch ($messageType) {
                case 'MeterValues':
                    $this->setMeterValue($payload);
                    $buffer = [
                        3,
                        $this->ReadPropertyString('messageID'),
                        []
                     ];
                    break;
                case 'StartTransaction':
                    //TODO not fully implemented / check is miss (Accepted or other)
                    $buffer = [
                        3,
                        $this->ReadPropertyString('messageID'),
                        [
                            'idTagInfo' => [
                                'status' => 'Accepted'
                            ],
                            'transactionId' => 50
                        ]
                    ];
                    $this->SetValue('transaction', true);
                    break;
                case 'StopTransaction':
                    //TODO not fully implemented check if it accepted miss
                    $buffer = [
                        3,
                        $this->ReadPropertyString('messageID'),
                        [
                            'idTagInfo' => [
                                'status' => 'Accepted'
                            ]
                        ]
                    ];
                    $this->SetValue('transaction', false);
                    break;
                case 'Heartbeat':
                    $buffer = [
                        3,
                        $this->ReadPropertyString('messageID'),
                        [
                            'currentTime' => date(DateTime::ISO8601)
                        ]
                    ];
                    break;
                default:
                    # code...
                    break;
            }
            if(isset($buffer)){
                $this->SendDataToParent(json_encode(['DataID' => '{8B051B38-91B7-97B3-2F99-BCB86C0925FA}', 'Buffer' => $buffer]));
            }            
        }

        private function setMeterValue($message)
        {
            $values = $message->meterValue;
            $values = json_encode($values);
            $values = json_decode($values, true);

            $currentValue = 0;
            $currentTime = 0;
            foreach ($values as $value) {
                //Get the timestamp
                $timestamp = $value['timestamp'];
                preg_match("/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z/", $timestamp, $matches);
                $unix = mktime(intval($matches[4]), intval($matches[5]), intval($matches[6]), intval($matches[2]), intval($matches[3]), intval($matches[1]));

                //Is the timestamp heighten than the prev save the it and the value
                if ($timestamp > $currentTime) {
                    $currentTime = $timestamp;
                    $currentValue = array_sum(array_column($value['sampledValue'], 'value'));
                }
            }
            $this->SetValue('meterValue', $currentValue);
        }
    }
