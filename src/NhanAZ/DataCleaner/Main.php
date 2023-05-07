<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaner;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {

	private function getExceptionData(): array {
		return array_merge($this->getConfig()->get("exceptionData", []), [".", ".."]);
	}

	private function deleteMessage(array $deleted): void {
		$this->getLogger()->info("§fDeleted data (" . count($deleted) . "): §a" . implode("§f,§a ", $deleted));
	}

	/**
	 * @param bool $justEmpty (only for folders) true delete the folder only if it is empty or false delete the folder and all its contents
	 *
	 * @return bool true on success or false on failure.
	 */
	public function delete(\DirectoryIterator $fileInfo, bool $justEmpty = false): bool {
		if ($fileInfo->isDir()) {
			return $this->deleteFolder($fileInfo, $justEmpty);
		} elseif ($fileInfo->isFile()) {
			return $this->deleteFile($fileInfo);
		}

		throw new \InvalidArgumentException($fileInfo->getFilename() . " is a " . $fileInfo->getType() . " but he must be a file or folder");
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function deleteFile(\DirectoryIterator $file): bool {
		if (!$file->isFile()) {
			throw new \InvalidArgumentException($file->getFilename() . " is a " . $file->getType() . " but he must be a file");
		} elseif (in_array($file->getFilename(), $this->getExceptionData(), true)) {
			return false;
		}

		return @unlink($file->getPathname());
	}

	/**
	 * @param bool $justEmpty true delete the folder only if it is empty or false delete the folder and all its contents
	 *
	 * @return bool true on success or false on failure.
	 */
	public function deleteFolder(\DirectoryIterator $folder, bool $justEmpty = false): bool {
		if (!$folder->isDir()) {
			throw new \InvalidArgumentException($folder->getFilename() . " is a " . $folder->getType() . " but he must be a folder");
		} elseif (in_array($folder->getFilename(), $this->getExceptionData(), true)) {
			return false;
		}

		if ($justEmpty) {
			$directoryIterator = new \DirectoryIterator($folder->getPathname());
			foreach ($directoryIterator as $fileInfo) {
				if ($fileInfo->isFile()) return false;
				if (!$fileInfo->isDot()) {
					if (!$this->deleteFolder($fileInfo, true)) {
						return false;
					}
				}
			}

			$filePathName = $folder->getPathname();
			// Check if is empty
			if (count(scandir($filePathName)) <= 2) {
				return @rmdir($filePathName);
			}
		} elseif ($this->deleteFilesInFolder($folder)) {
			return @rmdir($folder->getPathname());
		}

		return false;
	}

	/**
	 * @return bool true on success or false on failure.
	 */
	public function deleteFilesInFolder(\DirectoryIterator $folder): bool {
		$result = true;
		$directoryIterator = new \DirectoryIterator($folder->getPathname());
		foreach ($directoryIterator as $fileInfo) {
			$result = $result && $this->delete($fileInfo, false);
		}

		return $result;
	}

	protected function onEnable(): void {
		$this->saveDefaultConfig();
		if ($this->getServer()->getConfigGroup()->getProperty("plugins.legacy-data-dir")) {
			$this->getLogger()->warning("legacy-data-dir is true, please set it to false in the pocketmine.yml");
			return;
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
			$plugins = array_map(
				function (Plugin $plugin): string {
					return $plugin->getDescription()->getName();
				},
				$this->getServer()->getPluginManager()->getPlugins()
			);

			$deleted = [];
			$directoryIterator = new \DirectoryIterator($this->getServer()->getDataPath() . "plugin_data" . DIRECTORY_SEPARATOR);
			foreach ($directoryIterator as $fileInfo) {
				$fileName = $fileInfo->getFilename();
				$success = $this->delete($fileInfo, in_array($fileName, $plugins, true));
				if ($success) {
					array_push($deleted, $fileName);
				}
			}
			$this->deleteMessage($deleted);
		}), $this->getConfig()->get("delayTime", 1) * 20);
	}
}
