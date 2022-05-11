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

Python alternative (binary protocol):
* https://github.com/pklaus/smrt/
