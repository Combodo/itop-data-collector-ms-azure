<?php

class AzureCollectionPlan {
	public function CollectorToBeLaunched($sAzureClass): bool {
		switch ($sAzureClass) {
			case 'Subscription':
				return true;
			default:
				return false;
		}
	}

	public static function GetSubscriptionsToConsider(): array {
		return ['187cdd35-1a16-4c70-b4d6-099d827bce99'];
	}

	public static function GetResourceGroupsToConsider(): array {
		$sSubs = array(
			'187cdd35-1a16-4c70-b4d6-099d827bce99' => array(
				'RRS' => array(
					'cloud-shell-storage-westeurope',
					'combodo',
					'NetworkWatcherRG',
					'DefaultResourceGroup-PAR',
					'DefaultResourceGroup-PAR',
				),
			),
		);

		return $sSubs;
	}
}
