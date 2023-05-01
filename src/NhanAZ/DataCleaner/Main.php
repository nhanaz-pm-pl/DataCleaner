<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {

	// Sorry Poggit Reviewers, The code of this plugin is not clean!

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

	public function deleteFilesInFolder(string $path): void {
		foreach (new \DirectoryIterator($path) as $fileInfo) {
			if (!$fileInfo->isDot()) {
				if (!$fileInfo->isDir()) {
					unlink($fileInfo->getPathname());
				} else {
          $this->deleteFilesInFolder($fileInfo->getPathname());
          rmdir($fileInfo->getPathname());
        }
			}
		}
	}

	public function deleteEmptyFolder(string $path): void {
		foreach (new \DirectoryIterator($path) as $fileInfo) {
			$fileName = $fileInfo->getFilename();
			if (!in_array($fileName, $this->getExceptionData())) {
				if ($fileInfo->isDir()) {
					// Check if is empty
					if (count(scandir($fileInfo->getPathname())) <= 2) {
						rmdir($fileInfo->getPathname());
						array_push($this->deletedData, $fileInfo->getPathname());
					} else {
						$this->deleteEmptyFolder($fileInfo->getPathname());
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
			$this->getLogger()->warning("legacy-data-dir is true, please set it to false in the pocketmine.yml");
			return;
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			$this->deleteEmptyFolder($this->getDataPath());
			$plugins = array_map(
				function (Plugin $plugin): string {
					return $plugin->getDescription()->getName();
				},
				$this->getServer()->getPluginManager()->getPlugins()
			);
			foreach (new \DirectoryIterator($this->getDataPath()) as $fileInfo) {
				$fileName = $fileInfo->getFilename();
				if (!in_array($fileName, $plugins, true)) {
					if (!in_array($fileName, $this->getExceptionData(), true)) {
						$this->deleteFilesInFolder($fileInfo->getPathname());
						rmdir($fileInfo->getPathname());
						array_push($this->deletedData, $fileName);
					}
				}
			}
			$this->deleteMessage();
		}), $this->getConfig()->get("delayTime") * 20);
	}

	/**
	 * @priority LOWEST
	 */
	protected function onDisable(): void {
		if ($this->getServer()->getConfigGroup()->getProperty("plugins.legacy-data-dir")) {
			$this->getLogger()->warning("legacy-data-dir is true, please set it to false in the pocketmine.yml");
			return;
		}
		$this->deleteEmptyFolder($this->getDataPath());
	}
}
