<?php
require_once(APPROOT.'collectors/AzureCollectionPlan.class.inc.php');
require_once(APPROOT.'collectors/AzureJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureSubscriptionJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureLocationJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureResourceGroupJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureLoadBalancerJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVirtualMachineJsonCollector.class.inc.php');

// List of collector to potentially launch. Warning: order is important
$aCollectorClasses = [
	'AzureSubscriptionJsonCollector',       // Subscriptions
	'AzureLocationJsonCollector',           // Locations
	'AzureResourceGroupJsonCollector',      // Resources group
	'AzureLoadBalancerJsonCollector',       // Load Balancers
	'AzureVirtualMachineJsonCollector',     // Virtual Machines
];

// Initialize collection plan
$oAzureCollectionPlan = new AzureCollectionPlan();

// Collect objects as required
$iIndex = 1;
foreach ($aCollectorClasses as $sCollectorClass) {
	if ($oAzureCollectionPlan->CollectorToBeLaunched($sCollectorClass)) {
		Orchestrator::AddCollector($iIndex++, $sCollectorClass);
	}
}
