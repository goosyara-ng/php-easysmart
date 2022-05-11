# php-easysmart
TP-Link Easy Smart status fetcher library for UserSide ERP (PHP version). Retrieves device status via the switch HTTPd service (port 80/tcp).

Available modes (read only):
* Switch port info (number, enabled/disabled, activity, speed, data transferred TX/RX)
* VLAN table (id, name, port membership). Works only with 802.1Q VLAN (TP-Link Easy Smart switches also have some another port grouping modes: MTU VLAN and Port Based VLAN)

Supported switches:
* TL-SG105E (except REV V1, tested)
* TL-SG108E (except REV V1, tested)
* TL-SG108PE (tested)
* TL-SG1016DE (tested)
* TL-SG1024DE (tested)

## API versions
The library supports two version of HTTP API (based on hardware versions):
* 1 - REV V1 (only TL-SG1016DE and TL-SG1024DE)
* 2 - REV V2 and later

The switch models TL-SG105E and TL-SG108E of the first hardware revision can be managed only by binary protocol (see Alternatives).

## Alternatives
Python alternative (binary protocol):
* https://github.com/pklaus/smrt/
