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
        $this->RegisterPropertyString('ChargePointIdentity', '');

        //Variables
        $this->RegisterVariableString('Vendor', $this->Translate('Vendor'));
        $this->RegisterVariableString('Model', $this->Translate('Model'));
        $this->RegisterVariableString('SerialNumber', $this->Translate('SerialNumber'));

        $this->RegisterVariableFloat('MeterValue', $this->Translate('Meter Value'), '~Electricity.Wh');
        $this->RegisterVariableBoolean('Transaction', $this->Translate('Transaction run'));
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

        $this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('ChargePointIdentity') . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $this->SendDebug('Received', json_encode($data['Message']), 0);
        $messageID = $this->ReadPropertyString('MessageID');
        $messageType = $data['Message'][2];
        $payload = $data['Message'][3];
        //$this->SendDebug('messageType', $messageType, 0);
        switch ($messageType) {
            case 'BootNotification':
                $this->SetValue('Vendor', $payload['chargePointVendor']);
                $this->SetValue('Model', $payload['chargePointModel']);
                $this->SetValue('SerialNumber', $payload['chargePointSerialNumber']);
                // No Feedback. Feedback is send by the Splitter
                break;
            case 'MeterValues':
                $this->setMeterValue($payload);
                $message = $this->getMeterValueResponse($messageID);
                break;
            case 'StartTransaction':
                //TODO not fully implemented / check is miss (Accepted or other)
                $message = $this->getStartTransactionResponse($messageID);
                $this->SetValue('Transaction', true);
                break;
            case 'StopTransaction':
                //TODO not fully implemented check if it accepted miss
                $message = $this->getStopTransactionResponse($messageID);
                $this->SetValue('Transaction', false);
                break;
            case 'Heartbeat':
                $message = $this->getHeartbeatResponse($messageID);
                break;
            default:
                break;
        }

        if (isset($message)) {
            $this->SendDebug('Transmitted', json_encode($message), 0);
            $this->SendDataToParent(json_encode([
                'DataID' => '{8B051B38-91B7-97B3-2F99-BCB86C0925FA}',
                'ChargePointIdentity' => $this->ReadPropertyString('ChargePointIdentity'),
                'Message' => $message
            ]));
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
        $this->SetValue('MeterValue', $currentValue);
    }
}
