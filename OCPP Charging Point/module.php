<?php

declare(strict_types=1);

include __DIR__ . '/../libs/OCPPConstants.php';

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
        $message = json_decode($JSONString, true);
        $this->SendDebug('Received', json_encode($message['Message']), 0);
        $messageID = $message['Message'][1];
        $messageType = $message['Message'][2];
        $payload = $message['Message'][3];

        switch ($messageType) {
            case 'BootNotification':
                $this->SetValue('Vendor', $payload['chargePointVendor']);
                $this->SetValue('Model', $payload['chargePointModel']);
                $this->SetValue('SerialNumber', $payload['chargePointSerialNumber']);
                // No Feedback. Feedback is sent by the Splitter
                break;
            case 'MeterValues':
                $this->processMeterValue($messageID, $payload);
                break;
            case 'StatusNotification':
                $this->processStatusNotification($messageID, $payload);
                break;
            case 'StartTransaction':
                $this->processStartTransaction($messageID, $payload);
                break;
            case 'StopTransaction':
                $this->processStopTransaction($messageID, $payload);
                break;
            case 'Heartbeat':
                $this->send($this->getHeartbeatResponse($messageID));
                break;
            case 'DataTransfer':
                $this->send($this->getDataTransferResponse($messageID));
                break;
            default:
                break;
        }
    }

    private function send($message)
    {
        $this->SendDebug('Transmitted', json_encode($message), 0);
        $this->SendDataToParent(json_encode([
            'DataID'              => '{8B051B38-91B7-97B3-2F99-BCB86C0925FA}',
            'ChargePointIdentity' => $this->ReadPropertyString('ChargePointIdentity'),
            'Message'             => $message
        ]));
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

    private function getStatusNotificationResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 73
         * StatusNotification.conf
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
                'currentTime' => date(DateTime::ATOM)
            ]
        ];
    }

    private function processMeterValue(string $messageID, $payload)
    {
        $values = $payload['meterValue'];

        $currentValue = null;
        $currentTime = 0;
        foreach ($values as $value) {
            //If the timestamp is higher than the previous, save it and the value. We only want the newest one
            $newTime = strtotime($value['timestamp']);
            if ($newTime > $currentTime) {
                $currentTime = $newTime;
                $currentValue = $value;
            }
        }
        
        foreach ($currentValue['sampledValue'] as $sampledValue) {
            $suffix_ident = "";
            $suffix_name = "";
            if (isset($sampledValue['measurand'])) {
                $suffix_ident = str_replace('.', '_', $sampledValue['measurand']);
                $suffix_name = ", " . $sampledValue['measurand'];
            }
            
            $ident = sprintf('MeterValue_%d%s', $payload['connectorId'], $suffix_ident);
            $this->RegisterVariableFloat($ident, sprintf($this->Translate('Meter Value (Connector %d)%s'), $payload['connectorId'], $suffix_name), '~Electricity.Wh');
            $this->SetValue($ident, $sampledValue['value']);
        }
        
        $this->send($this->getMeterValueResponse($messageID));
    }

    private function processStatusNotification(string $messageID, $payload)
    {
        $ident = sprintf('Status_%d', $payload['connectorId']);
        $this->RegisterVariableString($ident, sprintf($this->Translate('Status (Connector %d)'), $payload['connectorId']));
        $this->SetValue($ident, $payload['status']);

        $ident = sprintf('ErrorCode_%d', $payload['connectorId']);
        $this->RegisterVariableString($ident, sprintf($this->Translate('ErrorCode (Connector %d)'), $payload['connectorId']));
        $this->SetValue($ident, $payload['errorCode']);
        
        $this->send($this->getStatusNotificationResponse($messageID));
    }
    
    private function processStartTransaction(string $messageID, $payload)
    {
        $ident = sprintf('Transaction_%d', $payload['connectorId']);
        $this->RegisterVariableBoolean($ident, sprintf($this->Translate('Transaction (Connector %d)'), $payload['connectorId']));
        $this->SetValue($ident, true);
        
        $this->send($this->getStartTransactionResponse($messageID));
    }

    private function processStopTransaction(string $messageID, $payload)
    {
        $ident = sprintf('Transaction_%d', $payload['connectorId']);
        $this->RegisterVariableBoolean($ident, sprintf($this->Translate('Transaction (Connector %d)'), $payload['connectorId']));
        $this->SetValue($ident, false);
        
        $this->send($this->getStopTransactionResponse($messageID));
    }

    private function getDataTransferResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 64
         * DataTransfer.conf
         */
        return [
            CALLRESULT,
            $messageID,
            [
                'status' => 'Accepted'
            ]
        ];
    }
}
