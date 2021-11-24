<?php
require_once(APPROOT.'collectors/AzureJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureSubscriptionJsonCollector.class.inc.php');

//$oCollector = new AzureSubscriptionJsonCollector();
//$oCollector->prepare();
Orchestrator::AddCollector(1, 'AzureSubscriptionJsonCollector');


