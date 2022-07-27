<?php

declare(strict_types=1);

namespace NhanAZ\DataCleaning;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {

	const CONFIG_VERSION = "1.0.0";

	// It's a mess, please don't mind it :)
	// I'm sorry for the bad code, I'm new to PHP and I don't know how to make it better (Suggestions from the Github Copilot and it was telling the truth)
	// Want to clean up this mess XD? Create a pull request at: https://github.com/nhanaz-pm-pl/DataCleaning/pulls

	private function getDataPath() {
		return $this->getServer()->getDataPath() . "plugin_data" . DIRECTORY_SEPARATOR;
	}

	private function getExceptionData(): array {
		$exceptionData = $this->getConfig()->get("exceptionData",  [".", ".."]);
		return $exceptionData[] = [".", ".."];
	}

	private function deleteMessage(string $data, string $dataType) {
		if ($this->getConfig()->get("deleteMessageMode", true)) {
			$replacatements = [
				"{data}" => $data,
				"{dataType}" => $dataType
			];
			$deleteMessage = str_replace(
				array_keys($replacatements),
				array_values($replacatements),
				$this->getConfig()->get("deleteMessage", "&aDeleted: &b{data} &6[{dataType}]")
			);
			$this->getLogger()->info(TextFormat::colorize($deleteMessage));
		}
	}

	private function deleteDir($dir = null): void {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					$path = $dir . DIRECTORY_SEPARATOR . $object;
					if (filetype($path) == "dir") $this->deleteDir($path);
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
							$this->deleteMessage($data, "Empty folder");
						}
					}
				}
			}
		}
	}

	private function checkConfig(): void {
		$configVersion = $this->getConfig()->exists("version") ? $this->getConfig()->get("version") : "0.0.0";
		if (version_compare($configVersion, self::CONFIG_VERSION, "<>")) {
			$this->getLogger()->notice("Your configuration file is invalid, updating the config.yml...");
			$this->getLogger()->notice("Invalid configuration file can be found at config_invalid.yml");
			rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config_invalid.yml");
			$this->saveDefaultConfig();
			$this->getConfig()->reload();
		}
	}

	/**
	 * @priority LOWEST
	 */
	protected function onEnable(): void {
		$this->checkConfig();
		$this->saveDefaultConfig();
		if ((bool)$this->getServer()->getConfigGroup()->getProperty("plugins.legacy-data-dir")) {
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
							$this->deleteMessage($data, "Plugin folder doesn't exist");
						}
					}
				}
			}
		}), $this->getConfig()->get("delayTime", 1) * 20);
	}

	/**
	 * @priority LOWEST
	 */
	protected function onDisable(): void {
		$this->deleteEmptyFolder();
	}
}
