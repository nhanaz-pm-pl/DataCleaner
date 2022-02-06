<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaning;

use pocketmine\plugin\Plugin;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

	public function deleteDir($dir = null) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					$path = $dir . "/" . $object;
					if (filetype($path) == "dir") $this->deleteDir($path);
					else unlink($path);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}

	protected function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveDefaultConfig();
		$plugins = array_map(function (Plugin $plugin): string {
			return $plugin->getDescription()->getName();
		}, $this->getServer()->getPluginManager()->getPlugins());
		$dataPath = $this->getServer()->getDataPath() . "plugin_data";
		foreach (scandir($dataPath) as $data) {
			if (!in_array($data, $plugins)) {
				$exceptionData = $this->getConfig()->get("exceptionData");
				if (!in_array($data, $exceptionData)) {
					if (is_dir($dataPath . "/" . $data)) {
						$this->deleteDir($dataPath . "/" . $data);
						$this->getLogger()->notice("Deleted folder: " . $data);
					}
				}
			}
		}
	}
}
