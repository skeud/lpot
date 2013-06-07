# Liip (unofficial) PO Toolbox (lpot)

## Installation
* `cd path/where/you/want/lpot`
* `git clone git@github.com:skeud/lpot.git && cd lpot && curl -sS https://getcomposer.org/installer | php && php composer.phar install`
* `cp src/config-dist.yml src/config.yml`
* Replace content of src/config.yml with your adapted config (examples here: http://liip.to/lpot_config_examples)
* **That's it**

## Usage
* Get the current week team availability in MD
    * `./console planning:availability`
* Get the next 4 weeks team availability in MD
    * `./console planning:availability --startDate=2013-05-06 --endDate=2013-05-19`
* Command line output example
![lpot output example](/images/lpot_example.png)

## Config example
* http://liip.to/lpot_config_examples