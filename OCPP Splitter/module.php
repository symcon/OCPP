<?php

declare(strict_types=1);

define('CALLRESULT', 3); //OCPP-j-1.6-specification.pdf Page 12

include_once __DIR__ . '/../libs/WebHookModule.php';

    class OCPPSplitter extends WebHookModule
    {
        public function __construct($InstanceID)
        {
            parent::__construct($InstanceID, 'ocpp/' . $InstanceID);
        }

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyString('Address', 'ws://127.0.0.1:3777/hook/ocpp/' . $this->InstanceID);
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ForwardData($data)
        {
            $data = json_decode($data);
            $buffer = json_encode($data->Buffer);
            $this->SendDebug('Send Data', print_r($buffer, true), 0);

            $this->send($buffer);
        }

        private function send($package)
        {
            $package = json_encode($package);
            WC_PushMessage(IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}')[0], '/hook/ocpp/' . $this->InstanceID, $package);
        }

        private function getBootNotificationResponse(string $messageID){
            return [
                CALLRESULT,
                $messageID,
                [
                    'status'      => 'Accepted',
                    'currentTime' => date(DateTime::ISO8601),
                    'interval'    => 60
                ]
            ];
        }

        protected function ProcessHookData()
        {
            //Get the input
            $input = json_decode(file_get_contents('php://input'));
            $this->SendDebug('Data', json_encode($input), 0);
            //Send it to the children
            $this->SendDataToChildren(json_encode(['DataID'=> '{54E04042-D715-71A0-BA80-ADD8B6CDF151}', 'Buffer' => $input]));

            /**
             * OCPP-j-1.6-specification Page 13
             * Input[1] is the MessageID
             * Input[2] is the MessageType
             * 
             * Switch because there can be more MessageTypes
             */
            switch ($input[2]) {
                case 'BootNotification':
                    $this->SendDebug("Hi","hshg",0);
                    $package = $this->getBootNotificationResponse($input[1]);
                    break;
                
                default:
                    $package = "";
                    break;
            }

           $this->send($package);

        }
    }
