# org.stadtlandbeides.itemmanager

![Screenshot](/images/renew.png)

The Itemmanager has three main features:
* Update the Line items based on updated price field values
* Definition of a successor price set and/or price field values
* Renew the membership with payment plans based on the defined price field successor

It cooperates with the Membership Extras Extension. (https://civicrm.org/extensions/membership-extras)

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Use Case
The extension is made to use CiviCRM for CSA (Consumer Supported Agriculture).
The price fields are items used for the weekly delivery of food. 
So there is need to change the price information on regular base.

## Requirements

* PHP v7.2+
* CiviCRM 5.24
* uk.co.compucorp.membershipextras 2.0.0

## Installation (Web UI)

Learn more about installing CiviCRM extensions in the [CiviCRM Sysadmin Guide](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/).

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl org.stadtlandbeides.itemmanager@https://github.com/FIXME/org.stadtlandbeides.itemmanager/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/FIXME/org.stadtlandbeides.itemmanager.git
cv en itemmanager
```

## Getting Started

(* FIXME: Where would a new user navigate to get started? What changes would they see? *)

## Known Issues

(* FIXME *)

## Credits

This extension integrates functionality from the [Line Item Editor](https://lab.civicrm.org/extensions/lineitemedit) extension (`biz.jmaconsulting.lineitemedit`) by **Monish Deb**, **Joe Murray** and **JMA Consulting** (support@jmaconsulting.biz). The line item add, edit and cancel features are based on their work, licensed under [AGPL-3.0](http://www.gnu.org/licenses/agpl-3.0.html).
