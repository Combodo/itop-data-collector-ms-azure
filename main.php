<?php
require_once(APPROOT.'collectors/AzureCollectionPlan.class.inc.php');
require_once(APPROOT.'collectors/AzureJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureSubscriptionJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureResourceGroupJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVirtualMachineJsonCollector.class.inc.php');

// Initialize collection plan
$oAzureCollectionPlan = new AzureCollectionPlan();

// Collect objects as required
$iIndex = 1;
if ($oAzureCollectionPlan->CollectorToBeLaunched('Subscription')) {
	Orchestrator::AddCollector($iIndex++, 'AzureSubscriptionJsonCollector');
}
Orchestrator::AddCollector($iIndex++, 'AzureResourceGroupJsonCollector');
Orchestrator::AddCollector($iIndex++, 'AzureVirtualMachineJsonCollector');


