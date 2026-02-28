<?php
namespace Karachan;

use Karachan\Data\Driver\{CacheDriver, HttpDriver, ErrorLogLogDriver, FileLogDriver, LogDriver, StderrLogDriver};

defined('MEPHBOARD') or exit;

class Context {
	private array $definitions;

	public function __construct(array $definitions) {
		$this->definitions = $definitions;
	}

	public function get(string $name){
		if (!isset($this->definitions[$name])) {
			throw new \RuntimeException("Could not find a dependency named $name");
		}

		$ret = $this->definitions[$name];
		if (is_callable($ret) && !is_string($ret) && !is_array($ret)) {
			$ret = $ret($this);
			$this->definitions[$name] = $ret;
		}
		return $ret;
	}
}

function build_context(array $config): Context {
	return new Context([
		'config' => $config,
		LogDriver::class => function($c) {
			$config = $c->get('config');

			$name = $config['log_system']['name'];
			$level = $config['debug'] ? LogDriver::DEBUG : LogDriver::NOTICE;
			$backend = $config['log_system']['type'];

			if ($backend === 'file') {
				return new FileLogDriver($name, $level, $this->config['log_system']['file_path']);
			} elseif ($backend === 'stderr') {
				return new StderrLogDriver($name, $level);
			}
			return new ErrorLogLogDriver($name, $level);
		},
		HttpDriver::class => function($c) {
			$config = $c->get('config');
			return new HttpDriver($config['upload_by_url_timeout'], $config['max_filesize']);
		},
		CacheDriver::class => function($c) {
			// Use the global for backwards compatibility.
			return \cache::getCache();
		}
	]);
}
