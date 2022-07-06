<?php

declare(strict_types=1);

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
            $this->RegisterPropertyString('Address', 'ws://127.0.0.1:3777/hook/ocpp/'. $this->InstanceID);
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        protected function ProcessHookData()
        {
			//Get the input
			$input = json_decode(file_get_contents("php://input"));
			$this->SendDebug("Data", json_encode($input),0);
			//Send it to the children 
			$children = $this->SendDataToChildren(json_encode(['DataID'=> '{54E04042-D715-71A0-BA80-ADD8B6CDF151}', 'Buffer' => $input]));
			//$this->SendDebug('whitch children', print_r($children), 0);

			//If it is a BootNotification, send a confirmation to keep the handshake alive
            if ($input[2] == "BootNotification") {
                $buffer =
                [
                    3,
                    $input[1],
                    [
                        "status" => "Accepted",
                        "currentTime" => date(DateTime::ISO8601),
                        "interval" => 60
                    ]
                ];
                WC_PushMessage(IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}")[0], '/hook/ocpp/'. $this->InstanceID, json_encode($buffer));
            }
        }

		public function ForwardData($data){
            $data = json_decode($data);
            $buffer = json_encode($data->Buffer);
            $this->SendDebug("Send Data", print_r($buffer,true), 0);
			WC_PushMessage(IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}")[0], '/hook/ocpp'. $this->InstanceID, $buffer);
		}

		public function validateForParent(String $data)
		{
			//TODO Validate the data for OCPP
			return true;
		}

		public function validateForChildren(String $data)
		{
			//TODO Validate the data for OCPP
			return true;
		}
    }
