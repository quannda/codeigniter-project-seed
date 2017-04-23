<?php
/**
 * Part of CodeIgniter Composer Installer
 *
 * @author     Kenji Suzuki <https://github.com/kenjis>
 * @license    MIT License
 * @copyright  2015 Kenji Suzuki
 * @link       https://github.com/kenjis/codeigniter-composer-installer
 */

namespace QuanNDA\CodeIgniter;

use Composer\Script\Event;

class ProjectSeed
{
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
				$io->write('Folder '.$app['source'].' is already exist.');
			}
			if (is_dir($app['doc_root'])) {
				$safe_install = false;
				$io->write('Folder '.$app['doc_root'].' is already exist.');
			}
		}
		if(!$safe_install){
			$io->write('Not safe for install. Please remove all exist application folder and its doc_root');
			$io->write('Exit with error');
			return;
		}
		$io->write('==================================================');
		$io->write('Begin copy CodeIgniter files ...');
		foreach ($apps as $app) {
			// Copy CodeIgniter files
			mkdir($app['source'], 0755, true);
			mkdir($app['doc_root'], 0755, true);

			self::recursiveCopy('vendor/codeigniter/framework/application', $app['source']);

			copy('vendor/codeigniter/framework/index.php', __DIR__ . $app['public'] . '/index.php');
			copy('dot.htaccess', __DIR__ . $app['public'] . '/.htaccess');
			copy('vendor/codeigniter/framework/.gitignore', '.gitignore');

			//map doc_root to relative path
			$relative_folders = explode('/', trim($app['doc_root'], '/'));
			foreach ($relative_folders as &$folder) {
				$folder = '..';
			}
			$relative_folders = implode('/', $relative_folders);
			// Fix paths in index.php
			$file = __DIR__ . $app['public'] . '/index.php';
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
		$io->write('See <https://github.com/kenjis/codeigniter-composer-installer> for details');
		$io->write('==================================================');
	}

	private static function deleteSelf()
	{
		unlink(__FILE__);
		rmdir('src');
		unlink('composer.json.dist');
		unlink('dot.htaccess');
		unlink('LICENSE.md');
	}

	/**
	 * Recursive Copy
	 *
	 * @param string $src
	 * @param string $dst
	 */
	private static function recursiveCopy($src, $dst)
	{
		mkdir($dst, 0755);

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
}
