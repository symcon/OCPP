# OCPP Charging Point
Instanz, welches einen Ladepunkt repräsentiert. Zeigt den Verbrauch, sowie ob gerade eine Transaktion läuft, an.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)

### 1. Funktionsumfang

* Anzeige des Verbrauchs
* Anzeige, ob gerade eine Transaktion läuft

### 2. Voraussetzungen

- IP-Symcon ab Version 6.0

### 3. Software-Installation

* Über den Module Store das 'OCPP Charging Point'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'OCPP Charging Point'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name                  | Beschreibung
--------------------- | ------------------
Charge Point Identity | Einzigartiger Identifikator des Ladepunktes 

__Hinweise__
* Bei der Pulsar Plus müssen alle Zeitpläne vor der Aktivierung von OCPP entfernt werden. Die Ansteuerung über OCPP kann die konfigurierten Zeitpläne nicht überschreiben, da diese immer Vorrang haben.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name                  | Typ     | Beschreibung
--------------------- | ------- | ------------
Hersteller            | string  | Name des Herstellers
Verbrauchswert        | float   | Anzeige des Verbrauchs des Ladepunktes
Model                 | string  | Modell des Ladepunktes
Seriennummer          | string  | Seriennummer des Ladepunktes
Transaktion ausführen | boolean | Anzeige, ob gerade ein Ladepunkt genutzt wird

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`void OCPP_RemoteStartTransaction(integer $InstanzID, integer $ConnectorId);`

Wenn der Status auf 'Preparing' or 'Finishing' steht, kann durch diesen Befehl das Laden freigeschaltet werden.

Beispiel:
`OCPP_RemoteStartTransaction(12345, 1);`

`void OCPP_RemoteStopCurrentTransaction(integer $InstanzID, integer $ConnectorId);`

Wenn der Zustand auf 'Charging' steht, kann durch diesen Befehl das Laden beendet werden.
Es wird dabei die aktuelle TransactionId genommen, welche in der passenden Variable zur ConnectorId steht!

Beispiel:
`OCPP_RemoteStopCurrentTransaction(12345, 1);`

`void OCPP_RemoteStopTransaction(integer $InstanzID, integer $TransactionId);`

Wenn der Zustand auf 'Charging' steht, kann durch diesen Befehl das Laden beendet werden.
Dazu muss jedoch nicht die ConnectorId, sondern die TransactionId, welche in der passenden Variable steht, übergeben werden!

Beispiel:
`OCPP_RemoteStopTransaction(12345, 4479);`

