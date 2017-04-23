<?php
/**
 * Part of CodeIgniter Composer Installer
 *
 * @author     Quan NDA <https://github.com/quannda>
 * @license    MIT License
 * @copyright  2017 Quan NDA
 * @link       https://github.com/quannda/codeigniter-project-seed
 */

namespace QuanNDA\CodeIgniter;

use Composer\Script\Event;

class ProjectSeed
{
	/**
	 * Composer post create-project script
	 *
	 * @param Event $event
	 */
	public static function postCreateProject(Event $event = null)
	{
		copy('composer.json.init', 'composer.json');
		// Show config guide message
		self::showConfigGuide($event);
		// remove step 1 waste
		self::cleanSelf();
	}

	/**
	 * show help for Config project
	 *
	 * @param Event $event
	 */
	private static function showConfigGuide(Event $event = null)
	{
		$io = $event->getIO();
		$io->write('==================================================');
		$io->write(
			'<info>`composer.json` was installed. Please take a look on config/app in composer.json to make any change for best matching with your multi applications project.</info>'
		);
		$io->write('then run: composer install');
		$io->write('==================================================');
	}

	private static function cleanSelf()
	{
		unlink('composer.json.init');
	}

	/**
	 * Composer post install script
	 *
	 * @param Event $event
	 */
	public static function postInstall(Event $event = null)
	{
		$apps = $event->getComposer()->getConfig()->get('apps');
		if (!$apps) {
			$apps = array(
				array(
					'source'   => 'application',
					'doc_root' => 'public'
				)
			);
		}

		// check for all app folders were not exist
		$io = $event->getIO();
		$io->write('==================================================');
		$io->write('Checking for all app folders were not exist ...');
		$safe_install = true;
		foreach ($apps as $app) {
			if (is_dir($app['source'])) {
				$safe_install = false;
				$io->write('Folder ' . $app['source'] . ' is already exist.');
			}
			if (is_dir($app['doc_root'])) {
				$safe_install = false;
				$io->write('Folder ' . $app['doc_root'] . ' is already exist.');
			}
		}
		if (!$safe_install) {
			$io->write('Not safe for install. Please remove all exist application folder and its doc_root');
			$io->write('Exit with error');
			return;
		}
		$io->write('OK!');
		$io->write('==================================================');
		$io->write('Begin copy CodeIgniter files ...');
		foreach ($apps as $app_name => $app) {
			// Copy CodeIgniter files
			$io->write('Create folder for ' . $app_name);

			self::recursiveCopy('vendor/codeigniter/framework/application', $app['source'], $io);
			if (!is_dir($app['doc_root'])) {
				mkdir($app['doc_root'], 075, true);
			}
			copy('vendor/codeigniter/framework/index.php', $app['doc_root'] . '/index.php');
			copy('dot.htaccess', $app['doc_root'] . '/.htaccess');
			copy('vendor/codeigniter/framework/.gitignore', '.gitignore');

			//map doc_root to relative path
			$relative_folders = explode('/', trim($app['doc_root'], '/'));
			foreach ($relative_folders as &$folder) {
				$folder = '..';
			}
			$relative_folders = implode('/', $relative_folders);
			// Fix paths in index.php
			$file = $app['doc_root'] . '/index.php';
			$contents = file_get_contents($file);
			$contents = str_replace(
				'$system_path = \'system\';',
				"\$system_path = '{$relative_folders}/vendor/codeigniter/framework/system';",
				$contents
			);
			$contents = str_replace(
				'$application_folder = \'application\';',
				"\$application_folder = '{$relative_folders}/{$app['source']}';",
				$contents
			);
			file_put_contents($file, $contents);

			// Enable Composer Autoloader
			$file = $app['source'] . '/config/config.php';
			$contents = file_get_contents($file);
			$contents = str_replace(
				'$config[\'composer_autoload\'] = FALSE;',
				"\$config['composer_autoload'] = realpath(APPPATH . '{$relative_folders}/vendor/autoload.php');",
				$contents
			);

			// Set 'index_page' blank
			$contents = str_replace(
				'$config[\'index_page\'] = \'index.php\';',
				'$config[\'index_page\'] = \'\';',
				$contents
			);
			file_put_contents($file, $contents);
		}
		// Update composer.json
		copy('composer.json.dist', 'composer.json');

		// Run composer update
		self::composerUpdate();

		// Show message
		self::showMessage($event);

		// Delete unneeded files
		self::deleteSelf();
	}

	/**
	 * Recursive Copy
	 *
	 * @param string $src
	 * @param string $dst
	 */
	private static function recursiveCopy($src, $dst, $io)
	{
		$io->write('create folder ' . $dst);
		if (!is_dir($dst)) {
			mkdir($dst, 0755, true);
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isDir()) {
				mkdir($dst . '/' . $iterator->getSubPathName());
			} else {
				copy($file, $dst . '/' . $iterator->getSubPathName());
			}
		}
	}

	private static function composerUpdate()
	{
		passthru('composer update');
	}

	/**
	 * Composer post install script
	 *
	 * @param Event $event
	 */
	private static function showMessage(Event $event = null)
	{
		$io = $event->getIO();
		$io->write('==================================================');
		$io->write(
			'<info>`public/.htaccess` was installed. If you don\'t need it, please remove it.</info>'
		);
		$io->write(
			'<info>If you want to install translations for system messages or some third party libraries,</info>'
		);
		$io->write('$ cd <codeigniter_project_folder>');
		$io->write('$ php bin/install.php');
		$io->write('<info>Above command will show help message.</info>');
		$io->write('See <https://github.com/quannda/codeigniter-project-seed> for details');
		$io->write('==================================================');
	}

	private static function deleteSelf()
	{
		unlink(__FILE__);
		unlink('composer.json.dist');
		unlink('dot.htaccess');
		unlink('LICENSE.md');
	}
}
