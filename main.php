<?php

// List of Azure class collectors to potentially launch.
// --->>> Warning: order is important for some classes, the ones listed with numbers or letters; alphabetical order is ok for others <<<---
$sClassCollectors = [
	'AzureSubscriptionAzureCollector',                  // 1- Subscriptions
	'AzureLocationAzureCollector',                      // 2- Locations
	'AzureResourceGroupAzureCollector',                 // 3- Resources group
	'AzureAppServiceAzureCollector',                    // App Services
	'AzureKubernetesServiceAzureCollector',             // Kubernetes Services
	'AzureLoadBalancerAzureCollector',                  // Load Balancers
	'AzureMariaDBServerAzureCollector',                 // Maria DB Servers
	'AzureMariaDBAzureCollector',                       // Maria DBs
	'AzureMySQLServerAzureCollector',                   // MySQL DB Servers
	'AzureMySQLAzureCollector',                         // MySQL DBs
	'AzureMSSQLServerAzureCollector',                   // MS SQL DB Servers
	'AzureMSSQLAzureCollector',                         // MS SQL DBs
	'AzurePostgreServerAzureCollector',                 // PostgreSQL Servers
	'AzurePostgreAzureCollector',                       // PostgreSQL DBs
	'AzureStorageAccountAzureCollector',                // Storage Accounts
	'AzureVirtualMachineAzureCollector',                // a- Virtual Machines
	'AzureDiskAzureCollector',                          // b- Disks
	'AzureVNetAzureCollector',                          // c- Virtual Networks
	'AzureNetworkInterfaceAzureCollector',              // d- Network Interfaces
	'AzureSubnetAzureCollector',                        // e- Subnets
	'AzurePublicIPAddressAzureCollector',               // f- Public IP Addresses
	'AzureNetworkInterfaceIPConfigAzureCollector',      // g- Network Interface IP Configuration
	'AzureLoadBalancerFrontendIPConfigAzureCollector',  // h- Load Balancer Frontend IP Configuration
	'AzureVNetGatewayAzureCollector',                   // Virtual Network gateways
];

// Initialize collection plan
require_once(APPROOT.'collectors/AzureCollectionPlan.class.inc.php');
$oAzureCollectionPlan = new AzureCollectionPlan();

// Collect objects as required
$iIndex = 1;
Utils::Log(LOG_INFO, "---------- Azure Collectors to launched ----------");
foreach ($sClassCollectors as $sCollector) {
	require_once(APPROOT.'collectors/'.$sCollector.'.class.inc.php');
	if ($oAzureCollectionPlan->CollectorToBeLaunched($sCollector)) {
		Orchestrator::AddCollector($iIndex++, $sCollector);
	}
}
Utils::Log(LOG_INFO, "---------- Start collection process ----------");
