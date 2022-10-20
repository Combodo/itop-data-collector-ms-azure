<?php
require_once(APPROOT.'collectors/msbase/src/MSCollectionPlan.class.inc.php');
require_once(APPROOT.'collectors/msbase/src/MSJsonCollector.class.inc.php');

class AzureCollectionPlan extends MSCollectionPlan
{
	public $bTeemIpIsInstalled;
	public $bTeemIpZoneMgmtIsInstalled;
	public $bTeemIpNMEIsInstalled;

	/**
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Initialize collection plan
	 *
	 * @return void
	 * @throws \IOException
	 */
	public function Init()
	{
		parent::Init();

		// Fetch from iTop the list of subscriptions to discover
		Utils::Log(LOG_INFO, '---------- Fetch from iTop the list of Subscriptions to discover ----------');
		$oRestClient = new RestClient();
		try {
			$aResult = $oRestClient->Get('AzureSubscription', 'SELECT AzureSubscription WHERE discover_objects = \'yes\'');
			if ($aResult['code'] != 0) {
				Utils::Log(LOG_ERR, "{$aResult['message']} ({$aResult['code']})");
			} else {
				if (empty($aResult['objects'])) {
					// No object found
					Utils::Log(LOG_INFO, "There is no Azure subscription stored in iTop for which objects need to be discovered.");
				} else {
					foreach ($aResult['objects'] as $sKey => $aData) {
						$aAzureSubscriptionAttributes = $aData['fields'];
						$iSubscriptionId = $aAzureSubscriptionAttributes['subscriptionid'];
						$this->AddMSObjectsToConsider(['class' => MSJsonCollector::URI_PARAM_SUBSCRIPTION, 'id' => $iSubscriptionId], [], []);

						Utils::Log(LOG_INFO, 'Name: '.$aAzureSubscriptionAttributes['name'].' - ID: '.$iSubscriptionId);
					}
				}
			}
		} catch (Exception $e) {
			$sMessage = 'Cannot fetch subscriptions from iTop: '.$e->getMessage();
			if (is_a($e, "IOException")) {
				Utils::Log(LOG_ERR, $sMessage);
				throw $e;
			}
		}

		// Fetch from iTop the list of Resource Groups that belong to subscriptions to discover
		if ($this->IsSubscriptionToConsider()) {
			Utils::Log(LOG_INFO, '---------- Fetch from iTop the list of Resource groups ----------');
			$bFirstEntry = true;
			$sSubscriptionList = '';
			foreach ($this->aMSObjectsToConsider[MSJsonCollector::URI_PARAM_SUBSCRIPTION] as $sSubscription => $aSubscription) {
				$sSubscriptionList .= ($bFirstEntry) ? "'".$sSubscription."'" : ",'".$sSubscription."'";
				$bFirstEntry = false;
			}
			$oRestClient = new RestClient();
			try {
				$aResult = $oRestClient->Get('AzureResourceGroup',
					'SELECT AzureResourceGroup AS rg JOIN AzureSubscription AS s ON rg.azuresubscription_id =  s.id WHERE s.subscriptionid IN ('.$sSubscriptionList.')');
				if ($aResult['code'] != 0) {
					Utils::Log(LOG_ERR, "{$aResult['message']} ({$aResult['code']})");
				} else {
					if (empty($aResult['objects'])) {
						// No object found
						Utils::Log(LOG_INFO,
							"There is no Azure resource groups already stored in iTop within the subscriptions to discover.");
					} else {
						foreach ($aResult['objects'] as $sKey => $aData) {
							$aAzureResourceGroupAttributes = $aData['fields'];
							$sResourceGroupName = $aAzureResourceGroupAttributes['name'];
							$this->AddMSObjectsToConsider(['class' => MSJsonCollector::URI_PARAM_SUBSCRIPTION, 'id' => $aAzureResourceGroupAttributes['azuresubscription_subscriptionid']],
								['class' => MSJsonCollector::URI_PARAM_RESOURCEGROUP, 'id' => $sResourceGroupName], []);

							Utils::Log(LOG_INFO,
								'Subscription ID: '.$aAzureResourceGroupAttributes['azuresubscription_name'].' - Name: '.$sResourceGroupName);
						}
					}
				}
			} catch (Exception $e) {
				$sMessage = 'Cannot fetch subscriptions from iTop: '.$e->getMessage();
				if (is_a($e, "IOException")) {
					Utils::Log(LOG_ERR, $sMessage);
					throw $e;
				}
			}
		}

		// If TeemIp should be considered, check if it is installed or not
		Utils::Log(LOG_INFO, '---------- Check TeemIp / IPAM for iTop parameters ----------');
		$this->bTeemIpIsInstalled = false;
		$aTeemIpDiscovery = Utils::GetConfigurationValue('teemip_discovery', []);
		if (!empty($aTeemIpDiscovery) && isset($aTeemIpDiscovery['enable']) && ($aTeemIpDiscovery['enable'] == 'yes')) {
			Utils::Log(LOG_INFO, 'TeemIp should be considered. Detecting if it is installed on remote iTop server');
			$oRestClient = new RestClient();
			try {
				$aResult = $oRestClient->Get('IPAddress', 'SELECT IPAddress WHERE id = 0');
				if ($aResult['code'] == 0) {
					$this->bTeemIpIsInstalled = true;
					Utils::Log(LOG_INFO, 'Yes, TeemIp is installed');
				} else {
					Utils::Log(LOG_INFO, $sMessage = 'TeemIp is NOT installed');
				}
			} catch (Exception $e) {
				$sMessage = 'TeemIp is considered as NOT installed due to: '.$e->getMessage();
				if (is_a($e, "IOException")) {
					Utils::Log(LOG_ERR, $sMessage);
					throw $e;
				}
			}

			if ($this->bTeemIpIsInstalled) {
				// Check if TeemIp Zone Management is installed or not
				Utils::Log(LOG_INFO, 'Detecting if TeemIp Zone Management extension is installed on remote server');
				$this->bTeemIpZoneMgmtIsInstalled = false;
				$oRestClient = new RestClient();
				try {
					$aResult = $oRestClient->Get('Zone', 'SELECT Zone WHERE id = 0');
					if ($aResult['code'] == 0) {
						$this->bTeemIpZoneMgmtIsInstalled = true;
						Utils::Log(LOG_INFO, 'Yes, TeemIp Zone Management extension is installed');
					} else {
						Utils::Log(LOG_INFO, 'TeemIp Zone Management extension is NOT installed');
					}
				} catch (Exception $e) {
					$sMessage = 'TeemIp Zone Management extension is considered as NOT installed due to: '.$e->getMessage();
					if (is_a($e, "IOException")) {
						Utils::Log(LOG_ERR, $sMessage);
						throw $e;
					}
				}

				// Check if TeemIp Network Management Extended is installed or not
				Utils::Log(LOG_INFO, 'Detecting if TeemIp Network Management Extended extension is installed on remote server');
				$this->bTeemIpNMEIsInstalled = false;
				$oRestClient = new RestClient();
				try {
					$aResult = $oRestClient->Get('InterfaceSpeed', 'SELECT InterfaceSpeed WHERE id = 0');
					if ($aResult['code'] == 0) {
						$this->bTeemIpNMEIsInstalled = true;
						Utils::Log(LOG_INFO, 'Yes, TeemIp Network Management Extended is installed');
					} else {
						Utils::Log(LOG_INFO, 'TeemIp Network Management Extended is NOT installed');
					}
				} catch (Exception $e) {
					$sMessage = 'TeemIp Network Management Extended is considered as NOT installed due to: '.$e->getMessage();
					if (is_a($e, "IOException")) {
						Utils::Log(LOG_ERR, $sMessage);
						throw $e;
					}
				}
			}
		} else {
			Utils::Log(LOG_INFO, 'TeemIp should not be considered.');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function IsCollectorToBeLaunched($sCollector): bool
	{
		$aCollectorParams = Utils::GetConfigurationValue(strtolower($sCollector), []);
		if (!empty($aCollectorParams) && isset($aCollectorParams['enable']) && ($aCollectorParams['enable'] == 'yes')) {
			$aURIParameters = $sCollector::GetURIParameters();
			foreach ($aURIParameters as $index => $sParameter) {
				switch ($sParameter) {
					case MSJsonCollector::URI_PARAM_SUBSCRIPTION:
						if (!$this->IsSubscriptionToConsider()) {
							// All Azure objects being attached to a subscription, their discovery is only possible in the case where there is at least one subscription to discover.
							Utils::Log(LOG_INFO, $sCollector.' will NOT be launched as no subscription should be discovered');

							return false;
						}
						break;

					case MSJsonCollector::URI_PARAM_RESOURCEGROUP:
						if (!$this->IsResourceGroupToConsider()) {
							// If no resource group is already identified, let's check that discovery of resource group is enable.
							$aParamsResourceGroupJson = Utils::GetConfigurationValue(strtolower('AzureResourceGroupAzureCollector'), []);
							if (!isset($aParamsResourceGroupJson['enable']) || ($aParamsResourceGroupJson['enable'] != 'yes')) {
								Utils::Log(LOG_INFO, $sCollector.' will NOT be launched as no resource group should be discovered');

								return false;
							}
						}
						break;

					default:
						$aParamsParamClassJson = Utils::GetConfigurationValue(strtolower('Azure'.$sParameter.'AzureCollector'), []);
						if (!isset($aParamsParamClassJson['enable']) || ($aParamsParamClassJson['enable'] != 'yes')) {
							Utils::Log(LOG_INFO, $sCollector.' will not be launched as no '.$sParameter.' should be discovered');

							return false;
						}
						break;
				}
			}
			Utils::Log(LOG_INFO, $sCollector.' will be launched !');

			return true;
		}

		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function AddCollectorsToOrchestrator(): bool
	{
		Utils::Log(LOG_INFO, "---------- Azure Collectors to launched ----------");

		return parent::AddCollectorsToOrchestrator();
	}
}
