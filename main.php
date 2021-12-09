<?php
require_once(APPROOT.'collectors/AzureCollectionPlan.class.inc.php');
require_once(APPROOT.'collectors/AzureJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureAppServiceJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureCloudServiceJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureContainerRegistryJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureLoadBalancerJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureLocationJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureResourceGroupJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureSubscriptionJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVirtualMachineJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVNetJsonCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVNetGatewayJsonCollector.class.inc.php');

// List of collector to potentially launch.
// --->>> Warning: order is important for the first 3 classes; alphabetical order is ok afterwards <<<---
$aCollectorClasses = [
	'AzureSubscriptionJsonCollector',       // 1- Subscriptions
	'AzureLocationJsonCollector',           // 2- Locations
	'AzureResourceGroupJsonCollector',      // 3- Resources group
	'AzureAppServiceJsonCollector',         // App Services
	'AzureCloudServiceJsonCollector',       // Cloud Services
	'AzureContainerRegistryJsonCollector',  // Container Registries
	'AzureLoadBalancerJsonCollector',       // Load Balancers
	'AzureVirtualMachineJsonCollector',     // Virtual Machines
	'AzureVNetJsonCollector',               // Virtual Networks
	'AzureVNetGatewayJsonCollector',        // Virtual Network gateways
];

// Initialize collection plan
$oAzureCollectionPlan = new AzureCollectionPlan();

// Collect objects as required
$iIndex = 1;
Utils::Log(LOG_INFO, "---------- Azure Collectors to launched ----------");
foreach ($aCollectorClasses as $sCollectorClass) {
	if ($oAzureCollectionPlan->CollectorToBeLaunched($sCollectorClass)) {
		Orchestrator::AddCollector($iIndex++, $sCollectorClass);
	}
}
