<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {

	// Sorry Poggit Reviewers, The code of this plugin is not clean!

	private function getDataPath() {
		return $this->getServer()->getDataPath() . "plugin_data" . DIRECTORY_SEPARATOR;
	}

	private function getExceptionData(): array {
		$exceptionData = $this->getConfig()->get("exceptionData");
		return $exceptionData[] = [".", ".."];
	}

	private function deleteMessage(array $deleted): void {
		$this->getLogger()->info("§fDeleted data (" . count($deleted) . "): §a" . implode("§f,§a ", $deleted));
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function delete(\DirectoryIterator $fileInfo): bool {
		if ($fileInfo->isDir()) {
			return $this->deleteFolder($fileInfo);
		}

		return $this->deleteFile($fileInfo);
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function deleteFile(\DirectoryIterator $file): bool {
		if (in_array($file->getFilename(), $this->getExceptionData(), true)) {
			return false;
		}

		return unlink($file->getPathname());
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function deleteFolder(\DirectoryIterator $folder): bool {
		if (in_array($folder->getFilename(), $this->getExceptionData(), true)) {
			return false;
		}

		$this->deleteFilesInFolder($folder->getPathname());
		return rmdir($folder->getPathname());
	}

	public function deleteFilesInFolder(string $path): void {
		foreach (new \DirectoryIterator($path) as $fileInfo) {
			if (!in_array($fileInfo->getFilename(), $this->getExceptionData(), true)) {
				if (!$fileInfo->isDir()) {
					unlink($fileInfo->getPathname());
				} else {
					$this->deleteFilesInFolder($fileInfo->getPathname());
					rmdir($fileInfo->getPathname());
				}
			}
		}
	}

	/**
	 * @return string[]
	 * return all deleted files
	 */
	public function deleteEmptyFolder(string $path): array {
		$deleted = [];
		foreach (new \DirectoryIterator($path) as $fileInfo) {
			$fileName = $fileInfo->getFilename();
			if (!in_array($fileName, $this->getExceptionData())) {
				if ($fileInfo->isDir()) {
					// Check if is empty
					if (count(scandir($fileInfo->getPathname())) <= 2) {
						rmdir($fileInfo->getPathname());
						array_push($deleted, $fileInfo->getFilename());
					} else {
						if (!empty($this->deleteEmptyFolder($fileInfo->getPathname()))) {
							$deleted[] = $fileName;
						}
					}
				}
			}
		}

		return $deleted;
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
			$deleted = $this->deleteEmptyFolder($this->getDataPath());
			$plugins = array_map(
				function (Plugin $plugin): string {
					return $plugin->getDescription()->getName();
				},
				$this->getServer()->getPluginManager()->getPlugins()
			);
			foreach (new \DirectoryIterator($this->getDataPath()) as $fileInfo) {
				$fileName = $fileInfo->getFilename();
				if (!in_array($fileName, $plugins, true)) {
					if ($this->delete($fileInfo)) {
						array_push($deleted, $fileName);
					}
				}
			}
			$this->deleteMessage($deleted);
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
