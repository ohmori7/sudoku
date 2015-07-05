<?php
class Log {
	const EMERG	= 0;
	const ALERT	= 1;
	const CRIT	= 2;
	const ERR	= 3;
	const WARN	= 4;
	const NOTICE	= 5;
	const INFO	= 6;
	const DEBUG	= 7;

	private $logger;

	function
	__construct()
	{
		$format = '%timestamp% [%priorityName%]: %message%' . PHP_EOL;
		$formatter = new Zend_Log_Formatter_Simple($format);
		$writer = new Zend_Log_Writer_Stream('php://stderr');
		$writer->setFormatter($formatter);
		$writer->addFilter(Zend_Log::INFO);
		$this->logger = new Zend_Log();
		$this->logger->addWriter($writer);
	}

	private function
	log($level, $msg)
	{

		$this->logger($msg, $level);
	}

	public function
	emerg($msg)
	{
		$this->log(self::EMERG, $msg);
	}

	public function
	alert($msg)
	{
		$this->log(self::ALERT, $msg);
	}

	public function
	crit($msg)
	{
		$this->log(self::CRIT, $msg);
	}

	public function
	err($msg)
	{
		$this->log(self::ERR, $msg);
	}

	public function
	warn($msg)
	{
		$this->log(self::WARN, $msg);
	}

	public function
	notice($msg)
	{
		$this->log(self::NOTICE, $msg);
	}

	public function
	info($msg)
	{
		$this->log(self::INFO, $msg);
	}

	public function
	debug($msg)
	{
		$this->log(self::DEBUG, $msg);
	}
};
?>
