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
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ForwardData($data)
        {
            $this->send(json_decode($data)->Buffer);
        }

        protected function ProcessHookData()
        {
            $data = file_get_contents('php://input');
            $this->SendDebug('Receive', $data, 0);
            $data = json_decode($data);
            
            //Send it to the children
            $this->SendDataToChildren(json_encode(['DataID'=> '{54E04042-D715-71A0-BA80-ADD8B6CDF151}', 'Buffer' => $data]));

            /**
             * OCPP-j-1.6-specification Page 13
             * Input[1] is the MessageID
             * Input[2] is the MessageType
             *
             * Switch because there can be more MessageTypes
             */
            switch ($data[2]) {
                case 'BootNotification':
                    $this->send($this->getBootNotificationResponse($data[1]));
                    break;

                default:
                    break;
            }
        }

        public function GetConfigurationForm() {
            $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            
            $interfaces = Sys_GetNetworkInfo();
            $ipList = [];
            foreach ($interfaces as $interface) {
                $ipList[] = $interface['IP'];
            }
            
            $form['actions'][2]['caption'] = sprintf($this->Translate($form['actions'][2]['caption']), implode(', ', $ipList));
            $form['actions'][4]['caption'] = sprintf($this->Translate($form['actions'][4]['caption']), 'hook/ocpp/' . $this->InstanceID);
            return json_encode($form);
        }
        
        private function send($package)
        {
            $package = json_encode($package);
            $this->SendDebug('Transmit', $package, 0);
            WC_PushMessage(IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}')[0], '/hook/ocpp/' . $this->InstanceID, $package);
        }

        private function getBootNotificationResponse(string $messageID)
        {
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
    }
