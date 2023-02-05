<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {

	private array $deletedData = [];

	private function getDataPath() {
		return $this->getServer()->getDataPath() . "plugin_data" . DIRECTORY_SEPARATOR;
	}

	private function getExceptionData(): array {
		$exceptionData = $this->getConfig()->get("exceptionData");
		return $exceptionData[] = [".", ".."];
	}

	private function deleteMessage() {
		$deletedData = implode("§f,§a ", $this->deletedData);
		$dataCount = count($this->deletedData);
		$deleteMessage = "§fDeleted data ($dataCount): §a$deletedData";
		$this->getLogger()->info($deleteMessage);
	}

	private function deleteDir($dir = null): void {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					$path = $dir . DIRECTORY_SEPARATOR . $object;
					if (is_dir($path)) $this->deleteDir($path);
					else unlink($path);
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}

	private function deleteEmptyFolder(): void {
		$dataPath = $this->getDataPath();
		foreach (scandir($dataPath) as $data) {
			$exceptionData = $this->getExceptionData();
			if (!in_array($data, $exceptionData)) {
				foreach (array_diff(scandir($dataPath), [".", ".."]) as $data) {
					$dir = $dataPath . $data;
					if (is_dir($dir)) {
						if (is_readable($dir) && count(scandir($dir)) == 2) {
							rmdir($dir);
							array_push($this->deletedData, $data);
						}
					}
				}
			}
		}
	}

	/**
	 * @priority LOWEST
	 */
	protected function onEnable(): void {
		$this->saveDefaultConfig();
		if ($this->getServer()->getConfigGroup()->getProperty("plugins.legacy-data-dir")) {
			$this->getLogger()->warning("legacy-data-dir detected, please disable it in the pocketmine.yml");
			return;
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			$this->deleteEmptyFolder();
			$plugins = array_map(
				function (Plugin $plugin): string {
					return $plugin->getDescription()->getName();
				},
				$this->getServer()->getPluginManager()->getPlugins()
			);
			$dataPath = $this->getDataPath();
			foreach (scandir($dataPath) as $data) {
				if (!in_array($data, $plugins)) {
					$exceptionData = $this->getExceptionData();
					if (!in_array($data, $exceptionData)) {
						if (is_dir($dataPath . $data)) {
							$this->deleteDir($dataPath . $data);
							array_push($this->deletedData, $data);
						}
					}
				}
			}
			$this->deleteMessage($this->deletedData);
		}), $this->getConfig()->get("delayTime") * 20);
	}

	/**
	 * @priority LOWEST
	 */
	protected function onDisable(): void {
		$this->deleteEmptyFolder();
	}
}
