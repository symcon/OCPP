<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/OCPPConstants.php';
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
        $data = json_decode($data);
        $this->send($data->ChargePointIdentity, $data->Message);
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $interfaces = Sys_GetNetworkInfo();
        $ipList = [];
        foreach ($interfaces as $interface) {
            $ipList[] = $interface['IP'];
        }

        $form['actions'][3]['caption'] = sprintf($this->Translate($form['actions'][3]['caption']), implode(', ', $ipList));
        $form['actions'][5]['caption'] = sprintf($this->Translate($form['actions'][5]['caption']), $this->InstanceID);
        $form['actions'][7]['value'] = sprintf($this->Translate($form['actions'][7]['value']), implode('|', $ipList), $this->InstanceID);
        $form['actions'][8]['value'] = sprintf($this->Translate($form['actions'][8]['value']), implode('|', $ipList), $this->InstanceID);
        return json_encode($form);
    }

    protected function ProcessHookData()
    {
        $message = file_get_contents('php://input');
        $this->SendDebug('Receive', $message, 0);
        $message = json_decode($message);

        $prefix = '/hook/ocpp/' . $this->InstanceID . '/';

        if (strpos($_SERVER['REQUEST_URI'], $prefix) === false) {
            $this->SendDebug('Invalid', 'Hook is missing Charge Point Indentity', 0);
            return;
        }

        $chargePointIdentity = str_replace($prefix, '', $_SERVER['REQUEST_URI']);

        // At the moment we do not process any CALLRESULT/CALLERROR messages
        // Only TriggerMessage results will get them, and we do not process it for now
        if ($message[0] != CALL) {
            $this->SendDebug('Skipping', print_r($message, true), 0);
            return;
        }

        //Send it to the children
        $this->SendDataToChildren(json_encode([
            'DataID'              => '{54E04042-D715-71A0-BA80-ADD8B6CDF151}',
            'ChargePointIdentity' => $chargePointIdentity,
            'Message'             => $message
        ]));

        /**
         * OCPP-j-1.6-specification Page 13
         * Input[1] is the MessageID
         * Input[2] is the MessageType
         *
         * Switch because there can be more MessageTypes
         */
        switch ($message[2]) {
            case 'BootNotification':
                $this->send($chargePointIdentity, $this->getBootNotificationResponse($message[1]));
                break;

            default:
                break;
        }
    }

    private function send($chargePointIdentity, $message)
    {
        $message = json_encode($message);
        $this->SendDebug('Transmit', $message, 0);
        $id = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}')[0];
        WC_PushMessage($id, '/hook/ocpp/' . $this->InstanceID . '/' . $chargePointIdentity, $message);
    }

    private function getBootNotificationResponse(string $messageID)
    {
        return [
            CALLRESULT,
            $messageID,
            [
                'status'      => 'Accepted',
                'currentTime' => date(DateTime::ATOM),
                'interval'    => 60
            ]
        ];
    }
}
