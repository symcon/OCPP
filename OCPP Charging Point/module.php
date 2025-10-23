<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/OCPPConstants.php';

class OCPPChargingPoint extends IPSModule
{

    private const START_AUTOMATIC = -1;
    private const START_ID_ALL = 0;
    private const START_ID_CENTRAL = 1;
    private const START_ID_LOCAL = 2;
    private const START_ID_BOTH = 3;
    private const START_MANUALLY = 4;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyString('ChargePointIdentity', '');
        $this->RegisterPropertyInteger('ValidateIdTag', 0);
        $this->RegisterPropertyString('ValidIdTagList', '[]');

        //Variables
        $this->RegisterVariableString('Vendor', $this->Translate('Vendor'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 1);
        $this->RegisterVariableString('Model', $this->Translate('Model'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 2);
        $this->RegisterVariableString('SerialNumber', $this->Translate('Serial Number'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 3);
        $this->RegisterVariableString('IdTag', $this->Translate('Last Id Tag'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 4);
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

        // Filter only our ChargePoint
        $this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('ChargePointIdentity') . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $message = json_decode($JSONString, true);
        $this->SendDebug('Received', json_encode($message['Message']), 0);
        $messageID = $message['Message'][1];
        $messageType = $message['Message'][2];
        $payload = $message['Message'][3];

        $result = "";
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
                $result = json_encode($this->processStopTransaction($messageID, $payload));
                break;
            case 'Authorize':
                $this->processAuthorize($messageID, $payload);
                break;
            case 'ChangeAvailability':
                $this->processChangeAvailability($messageID, $payload);
                break;
            case 'Heartbeat':
                $this->send($this->getHeartbeatResponse($messageID));
                break;
            case 'DataTransfer':
                $this->send($this->getDataTransferResponse($messageID, 'Accepted'));
                break;
            default:
                break;
        }

        return $result;
    }

    public function RequestAction($Ident, $Value)
    {

        $ConnectorId = 0;
        $parts = explode('_', $Ident);
        if (count($parts) > 1) {
            $Ident = $parts[0];
            $ConnectorId = $parts[1];
        }

        switch ($Ident) {
            case 'Available':
                $this->send($this->getChangeAvailabilityRequest($ConnectorId, $Value ? 'Operative' : 'Inoperative'));
                break;
            default:
                throw new Exception('Invalid Ident');
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $form['elements'][2]['visible'] = in_array($this->ReadPropertyInteger('ValidateIdTag'), [self::START_ID_LOCAL, self::START_ID_BOTH]);
        return json_encode($form);
    }

    public function Migrate($configurationDataString) {
        $configurationData = json_decode($configurationDataString, true);
        if (isset($configurationData['configuration']['AutoStartTransaction']) && $configurationData['configuration']['AutoStartTransaction']) {
            $configurationData['configuration']['ValidateIdTag'] = self::START_AUTOMATIC;
            return json_encode($configurationData);
        }
        return "";
    }

    public function Update()
    {
        $this->send($this->getTriggerMessageRequest('BootNotification'));
        $this->send($this->getTriggerMessageRequest('MeterValues'));
        $this->send($this->getTriggerMessageRequest('StatusNotification'));
    }

    public function RemoteStartTransaction(int $ConnectorId)
    {
        $idTag = 'symcon';
        $this->send($this->getRemoteStartTransactionRequest($ConnectorId, $idTag));
    }

    public function RemoteStopTransaction(int $TransactionId)
    {
        $this->send($this->getRemoteStopTransactionRequest($TransactionId));
    }

    public function RemoteStopCurrentTransaction(int $ConnectorId)
    {
        $ident = sprintf('TransactionID_%d', $ConnectorId);

        // Some Wallboxes do not support proper TransactionID handling, but will react on TransactionID 0
        $id = @$this->GetIDForIdent($ident);
        if ($id === false) {
            $this->send($this->getRemoteStopTransactionRequest(0));
        } else {
            $this->send($this->getRemoteStopTransactionRequest(GetValue($id)));
        }
    }

    public function UIUpdateCP(int $ValidateIdTag)
    {
        $this->UpdateFormField('ValidIdTagList', 'visible', in_array($ValidateIdTag, [self::START_ID_LOCAL, self::START_ID_BOTH]));
    }

    private function getIdTagStatus($idTag)
    {
        $startStrategy = $this->ReadPropertyInteger('ValidateIdTag');
        if ($startStrategy == self::START_AUTOMATIC) {
            return 'Accepted';
        }

        // Our internal RemoteStartTransaction command was used
        if ($idTag == 'symcon') {
            return 'Accepted';
        }

        $centralIdTag = false;
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($parentID > 0) {
            $json = json_decode(IPS_GetProperty($parentID, 'ValidIdTagList'), true);
            foreach ($json as $item) {
                if ($idTag == $item['IdTag']) {
                    $centralIdTag = true;
                    break;
                }
            }
        }

        // Check if IdTag is in our lis
        $localIdTag = false;
        $json = json_decode($this->ReadPropertyString('ValidIdTagList'), true);
        foreach ($json as $item) {
            if ($idTag == $item['IdTag']) {
                $localIdTag = true;
                break;
            }
        }

        switch ($this->ReadPropertyInteger('ValidateIdTag')) {
            case self::START_ID_ALL:
                return 'Accepted';
            case self::START_ID_CENTRAL:
                if ($centralIdTag) {
                    return 'Accepted';
                }
                break;
            case self::START_ID_LOCAL:
                if ($localIdTag) {
                    return 'Accepted';
                }
                break;
            case self::START_ID_BOTH:
                if ($centralIdTag || $localIdTag) {
                    return 'Accepted';
                }
                break;
        }

        return 'Invalid';
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

    private function getStopTransactionResponse(string $messageID, string $status)
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
                    'status' => $status
                ]
            ]
        ];
    }

    private function getStartTransactionResponse(string $messageID, int $transactionId, string $status)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 76
         * StartTransaction.conf
         */

        return [
            CALLRESULT,
            $messageID,
            [
                'idTagInfo' => [
                    'status' => $status
                ],
                'transactionId' => $transactionId
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
        return [
            CALLRESULT,
            $messageID,
            new stdClass()
        ];
    }

    private function getStatusNotificationResponse(string $messageID)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 73
         * StatusNotification.conf
         */
        return [
            CALLRESULT,
            $messageID,
            new stdClass()
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
            $suffix_ident = '';
            $suffix_name = '';
            if (isset($sampledValue['measurand'])) {
                $suffix_ident = str_replace('.', '_', $sampledValue['measurand']);
                $suffix_name = ', ' . $sampledValue['measurand'];
            }

            $ident = sprintf('MeterValue_%d%s', $payload['connectorId'], $suffix_ident);
            $this->RegisterVariableFloat($ident, sprintf($this->Translate('Meter Value (Connector %d)%s'), $payload['connectorId'], $suffix_name), [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'SUFFIX'       => ' ' . ($sampledValue['unit'] ?? 'Wh')
            ], ($payload['connectorId'] + 1) * 100 + 10);
            $this->SetValue($ident, $sampledValue['value']);
        }

        $this->send($this->getMeterValueResponse($messageID));
    }

    private function processStatusNotification(string $messageID, $payload)
    {
        $ident = sprintf('Available_%d', $payload['connectorId']);
        $this->RegisterVariableBoolean($ident, sprintf($this->Translate('Available (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION'   => VARIABLE_PRESENTATION_SWITCH,
            'USAGE_TYPE'     => 0,
            'USE_ICON_FALSE' => false,
            'ICON_TRUE'      => 'plug'
        ], ($payload['connectorId'] + 1) * 100);
        $this->EnableAction($ident);
        $this->SetValue($ident, $payload['status'] != 'Unavailable');

        $ident = sprintf('Status_%d', $payload['connectorId']);
        $this->RegisterVariableString($ident, sprintf($this->Translate('Status (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], ($payload['connectorId'] + 1) * 100 + 1);
        $this->SetValue($ident, $payload['status']);

        $ident = sprintf('ErrorCode_%d', $payload['connectorId']);
        $this->RegisterVariableString($ident, sprintf($this->Translate('ErrorCode (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], ($payload['connectorId'] + 1) * 100 + 2);
        $this->SetValue($ident, $payload['errorCode']);

        $this->send($this->getStatusNotificationResponse($messageID));

        // Take care of the 'Preparing' status which might want to trigger us the RemoteStartTransaction
        if ($payload['status'] === 'Preparing') {
            if ($this->ReadPropertyInteger('ValidateIdTag') == self::START_AUTOMATIC) {
                $this->RemoteStartTransaction($payload['connectorId']);
            }
        }
    }

    private function processStartTransaction(string $messageID, $payload)
    {
        $ident = sprintf('Transaction_%d', $payload['connectorId']);
        $this->RegisterVariableBoolean($ident, sprintf($this->Translate('Transaction (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => json_encode([
                [
                    'Value'       => false,
                    'Caption'     => $this->Translate('Inactive'),
                    'IconActive ' => true,
                    'IconValue'   => 'bolt-slash',
                    'ColorActive' => false,
                    'ColorValue'  => ''
                ],
                [
                    'Value'       => true,
                    'Caption'     => $this->Translate('Active'),
                    'IconActive ' => true,
                    'IconValue'   => 'bolt',
                    'ColorActive' => false,
                    'ColorValue'  => ''
                ]
            ])
        ], ($payload['connectorId'] + 1) * 100 + 3);
        $this->SetValue($ident, true);

        // Transaction_* > OCPP Values
        // Transaction* > Internal values (without underscore!)

        $transactionId = $this->generateTransactionID();
        $ident = sprintf('TransactionID_%d', $payload['connectorId']);
        $this->RegisterVariableInteger($ident, sprintf($this->Translate('Transaction Id (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        ], ($payload['connectorId'] + 1) * 100 + 4);
        $this->SetValue($ident, $transactionId);

        $ident = sprintf('Transaction_Meter_Start_%d', $payload['connectorId']);
        $this->RegisterVariableInteger($ident, sprintf($this->Translate('Transaction Meter Start (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' Wh'
        ], ($payload['connectorId'] + 1) * 100 + 5);
        $this->SetValue($ident, $payload['meterStart']);

        $ident = sprintf('Transaction_Meter_End_%d', $payload['connectorId']);
        $this->RegisterVariableInteger($ident, sprintf($this->Translate('Transaction Meter End (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' Wh'
        ], ($payload['connectorId'] + 1) * 100 + 5);
        $this->SetValue($ident, 0);

        $ident = sprintf('Transaction_ID_Tag_%d', $payload['connectorId']);
        $this->RegisterVariableString($ident, sprintf($this->Translate('Transaction Id Tag (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], ($payload['connectorId'] + 1) * 100 + 6);

        // Workaround: Alfen is sending a wrong IdTag. We need to use the IdTag from the last authorization
        if ($this->GetValue('Vendor') == 'Alfen BV') {
            $payload['idTag'] = $this->GetValue('IdTag');
        }

        $this->SetValue($ident, $payload['idTag']);

        $ident = sprintf('TransactionConsumption_%d', $payload['connectorId']);
        $this->RegisterVariableInteger($ident, sprintf($this->Translate('Transaction Consumption (Connector %d)'), $payload['connectorId']), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' Wh'
        ], ($payload['connectorId'] + 1) * 100 + 5);
        $this->SetValue($ident, 0);

        $this->send($this->getStartTransactionResponse($messageID, $transactionId, $this->getIdTagStatus($payload['idTag'])));
    }

    private function processStopTransaction(string $messageID, $payload)
    {
        // Stop Transaction does not transmit the connectorId. We need to search it by the TransactionID.
        $connectorId = false;
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
            if (IPS_VariableExists($id)) {
                $o = IPS_GetObject($id);
                if (substr($o['ObjectIdent'], 0, 13) == 'TransactionID') {
                    if (GetValue($id) == $payload['transactionId']) {
                        $connectorId = str_replace('TransactionID_', '', $o['ObjectIdent']);
                    }
                }
            }
        }

        if ($connectorId === false) {
            $this->SendDebug('Error', 'TransactionID not found', 0);
            return;
        }

        // Update transaction values
        $this->SetValue(sprintf('Transaction_%d', $connectorId), false);
        $this->SetValue(sprintf('TransactionID_%d', $connectorId), 0);
        $this->SetValue(sprintf('Transaction_Meter_End_%d', $connectorId), $payload['meterStop']);
        $this->SetValue(sprintf('TransactionConsumption_%d', $connectorId), $payload['meterStop'] - $this->GetValue(sprintf('Transaction_Meter_Start_%d', $connectorId)));

        // The idTag might not be defined (Wallbox restarted and had to stop the transaction)
        // Therefore we can only validate if it is set (e.g. another RFID card was used to stop a running transaction)
        $status = isset($payload['idTag']) ? $this->getIdTagStatus($payload['idTag']) : 'Accepted';

        $this->send($this->getStopTransactionResponse($messageID, $status));

        // Return consumption data to properly forward it to the splitter
        return [
            "IdTag" => $this->GetValue(sprintf('Transaction_ID_Tag_%d', $connectorId)),
            "Consumption" => $this->GetValue(sprintf('TransactionConsumption_%d', $connectorId)),
        ];
    }

    private function processAuthorize(string $messageID, $payload)
    {
        $status = $this->getIdTagStatus($payload['idTag']);

        // We only want to remember the last successful IdTag
        // Normally the IdTag is only transmitted on StartTransaction,
        // but some ChargePoints (e.g. Alfen) do not transmit it there
        // Therefore we need to just remember it and use it there
        if ($status == 'Accepted') {
            $this->SetValue('IdTag', $payload['idTag']);
        }
        else {
            $this->SetValue('IdTag', '');
        }

        $this->send($this->getAuthorizeResponse($messageID, $status));
    }

    private function processChangeAvailability(string $messageID, $payload)
    {
        // Nothing to do yet
    }

    private function getDataTransferResponse(string $messageID, string $status)
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
                'status' => $status
            ]
        ];
    }

    private function getAuthorizeResponse(string $messageID, string $status)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 64
         * Authorize.conf
         */
        return [
            CALLRESULT,
            $messageID,
            [
                'idTagInfo' => [
                    'status' => $status
                ],
            ]
        ];
    }

    private function generateMessageID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function generateTransactionID()
    {
        return rand(1, 5000);
    }

    private function getTriggerMessageRequest(string $messageType)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 89
         * TriggerMessage.req
         */
        return [
            CALL,
            $this->generateMessageID(),
            'TriggerMessage',
            [
                'requestedMessage' => $messageType
            ]
        ];
    }

    private function getRemoteStartTransactionRequest(int $connectorId, string $idTag /* unsupported for now: array $chargingProfile */)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 80
         * RemoteStartTransaction.req
         */
        return [
            CALL,
            $this->generateMessageID(),
            'RemoteStartTransaction',
            [
                'connectorId' => $connectorId,
                'idTag'       => $idTag
            ]
        ];
    }

    private function getRemoteStopTransactionRequest(int $transactionId)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 81
         * RemoteStopTransaction.req
         */
        return [
            CALL,
            $this->generateMessageID(),
            'RemoteStopTransaction',
            [
                'transactionId' => $transactionId
            ]
        ];
    }

    private function getChangeAvailabilityRequest(int $connectorId, string $type)
    {
        /**
         * OCPP-1.6 edition 2.pdf
         * Page 65
         * ChangeAvailability.req
         */
        return [
            CALL,
            $this->generateMessageID(),
            'ChangeAvailability',
            [
                'connectorId' => $connectorId,
                'type'        => $type,
            ]
        ];
    }
}
