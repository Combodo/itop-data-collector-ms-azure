<?php

// List of collector to potentially launch.
// --->>> Warning: order is important for some 3 classes, the ones listed with numbers; alphabetical order is ok for others <<<---
$aCollectorClasses = [
	'AzureSubscriptionAzureCollector',      // 1- Subscriptions
	'AzureLocationAzureCollector',          // 2- Locations
	'AzureResourceGroupAzureCollector',     // 3- Resources group
	'AzureAppServiceAzureCollector',        // App Services
	'AzureKubernetesServiceAzureCollector', // Kubernetes Services
	'AzureLoadBalancerAzureCollector',      // Load Balancers
	'AzureMariaDBServerAzureCollector',     // Maria DB Servers
	'AzureMariaDBAzureCollector',           // Maria DBs
	'AzurePostgreServerAzureCollector',     // PostgreSQL Servers
	'AzurePostgreAzureCollector',           // PostgreSQL DBs
	'AzureStorageAccountAzureCollector',    // Storage Accounts
	'AzureVirtualMachineSizeAzureCollector',// a- Virtual Machine Sizes
	'AzureVirtualMachineAzureCollector',    // b- Virtual Machines
	'AzureDiskAzureCollector',              // c- Disks
	'AzureVNetAzureCollector',              // d- Virtual Networks
	'AzureNetworkInterfaceAzureCollector',  // e- Network Interfaces
	'AzureVNetGatewayAzureCollector',       // Virtual Network gateways
];

// Initialize collection plan
require_once(APPROOT.'collectors/AzureCollectionPlan.class.inc.php');
$oAzureCollectionPlan = new AzureCollectionPlan();

// Collect objects as required
$iIndex = 1;
Utils::Log(LOG_INFO, "---------- Azure Collectors to launched ----------");
foreach ($aCollectorClasses as $sCollectorClass) {
	require_once(APPROOT.'collectors/'.$sCollectorClass.'.class.inc.php');
	if ($oAzureCollectionPlan->CollectorToBeLaunched($sCollectorClass)) {
		Orchestrator::AddCollector($iIndex++, $sCollectorClass);
	}
}
