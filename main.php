<?php
require_once(APPROOT.'collectors/AzureCollectionPlan.class.inc.php');
require_once(APPROOT.'collectors/AzureAppServiceAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureCloudServiceAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureContainerRegistryAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureCosmosDBAccountAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureDiskAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureLoadBalancerAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureLocationAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureMariaDBServerAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureMariaDBAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureNetworkInterfaceAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureResourceGroupAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureStorageAccountAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureSubscriptionAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVirtualMachineAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVNetAzureCollector.class.inc.php');
require_once(APPROOT.'collectors/AzureVNetGatewayAzureCollector.class.inc.php');

// List of collector to potentially launch.
// --->>> Warning: order is important for some 3 classes, the ones listed with numbers; alphabetical order is ok for others <<<---
$aCollectorClasses = [
	'AzureSubscriptionAzureCollector',      // 1- Subscriptions
	'AzureLocationAzureCollector',          // 2- Locations
	'AzureResourceGroupAzureCollector',     // 3- Resources group
	'AzureAppServiceAzureCollector',        // App Services
	'AzureCloudServiceAzureCollector',      // Cloud Services
	'AzureCosmosDBAccountAzureCollector',   // Cosmos DB Accounts
	'AzureContainerRegistryAzureCollector', // Container Registries
	'AzureLoadBalancerAzureCollector',      // Load Balancers
	'AzureMariaDBServerAzureCollector',     // Maria DB Servers
	'AzureMariaDBAzureCollector',           // Maria DBs
	'AzureStorageAccountAzureCollector',    // Storage Accounts
	'AzureVirtualMachineAzureCollector',    // a- Virtual Machines
	'AzureDiskAzureCollector',              // b- Disks
	'AzureVNetAzureCollector',              // c- Virtual Networks
	'AzureNetworkInterfaceAzureCollector',  // d- Network Interfaces
	'AzureVNetGatewayAzureCollector',       // Virtual Network gateways
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
