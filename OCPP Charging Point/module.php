<?php

declare(strict_types=1);

define('CALLRESULT', 3);

class OCPPChargingPoint extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyString('Vendor', '');
        $this->RegisterPropertyString('Model', '');
        $this->RegisterPropertyString('SerialNumber', '');
        $this->RegisterPropertyString('MessageID', '');

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

        $this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('MessageID') . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        //$this->SendDebug('DATA', $JSONString, 0);
        $messageType = $data['Buffer'][2];
        $payload = $data['Buffer'][3];
        $messageID = $this->ReadPropertyString('MessageID');
        //$this->SendDebug('messageType', $messageType, 0);
        switch ($messageType) {
            case 'MeterValues':
                $this->setMeterValue($payload);
                $buffer = $this->getMeterValueResponse($messageID);
                break;
            case 'StartTransaction':
                //TODO not fully implemented / check is miss (Accepted or other)
                $buffer = $this->getStartTransactionResponse($messageID);
                $this->SetValue('transaction', true);
                break;
            case 'StopTransaction':
                //TODO not fully implemented check if it accepted miss
                $buffer = $this->getStopTransactionResponse($messageID);
                $this->SetValue('transaction', false);
                break;
            case 'Heartbeat':
                $buffer = $this->getHeartbeatResponse($messageID);
                break;
            default:
                # code...
                break;
        }

        if (isset($buffer)) {
            $this->SendDataToParent(json_encode(['DataID' => '{8B051B38-91B7-97B3-2F99-BCB86C0925FA}', 'Buffer' => $buffer]));
        }
    }

    private function getStopTransactionResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 77
         * StopTransaction.conf
         */
        return [
            CALLRESULT,
            $messageID,
            [
                'idTagInfo' => [
                    'status' => 'Accepted'
                ]
            ]
        ];
    }

    private function getStartTransactionResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 76
         * StartTrasaction.conf
         */

        return [
            CALLRESULT,
            $messageID,
            [
                'idTagInfo' => [
                    'status' => 'Accepted'
                ],
                'transactionId' => 50
            ]
        ];
    }

    private function getMeterValueResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 73
         * MeterValues.conf
         */
        return  [
            CALLRESULT,
            $messageID,
            []
        ];
    }

    private function getHeartbeatResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 72
         * Heartbeat.conf
         */
        return [
            CALLRESULT,
            $messageID,
            [
                'currentTime' => date(DateTime::ISO8601)
            ]
        ];
    }

    private function setMeterValue($message)
    {
        $values = $message['meterValue'];

        $currentValue = 0;
        $currentTime = 0;
        foreach ($values as $value) {
            //Get the timestamp
            //Timestamp is in ISO8601 
            $timestamp = $value['timestamp'];
            $unix = strtotime($timestamp);

            //If the timestamp is higher than the previous, save it and the value. We only want the newest one 
            if ($unix > $currentTime) {
                $currentTime = $unix;
                $currentValue = array_sum(array_column($value['sampledValue'], 'value'));
            }
        }
        $this->SetValue('meterValue', $currentValue);
    }
}
