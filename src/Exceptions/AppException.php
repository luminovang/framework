<?php
/**
 * Luminova Framework base exception class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

use \Luminova\Logger\Logger;
use \Luminova\Application\Foundation;
use \Luminova\Interface\ExceptionInterface;
use \Stringable;
use \Exception;
use \Throwable;

abstract class AppException extends Exception implements ExceptionInterface, Stringable
{
  /**
   * @var Logger|null $logger 
  */
  private static ?Logger $logger = null;

  /**
   * @var string|null $strCode 
  */
  private ?string $strCode = null;

  /**
   * {@inheritdoc}
  */
  public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }

  /**
   * {@inheritdoc}
  */
  public function getCodeString(): ?string 
  {
    return $this->strCode;
  }

  /**
   * {@inheritdoc}
  */
  public function setCodeString(string $code): self
  {
    $this->strCode = $code;
    return $this;
  }

  /**
   * {@inheritdoc}
  */
  public function __toString(): string
  {
    return "Exception: ({$this->code}) {$this->message} File: {$this->file}, Line: {$this->line}";
  }

  /**
   * {@inheritdoc}
  */
  public function handle(): void
  {
    if(Foundation::isCommand()){
      if(env('throw.cli.exceptions', false)){
        throw $this;
      }

      exit(self::display($this));
    }

    if(PRODUCTION && !Foundation::isFatal($this->code)) {
      $this->log();
      return;
    }

    throw $this;
  }

  /**
   * {@inheritdoc}
  */
  public static function throwException(string $message, int|string $code = 0, ?Throwable $previous = null): void
  {
    if(is_int($code)){
      (new static($message, $code , $previous))->handle();
      return;
    }
   
    (new static($message, 0, $previous))->setCodeString($code)->handle();
  }

  /**
   * {@inheritdoc}
  */
  public function log(string $level = 'exception'): void
  {
    self::$logger ??= new Logger(); 
    self::$logger->log($level, $this->__toString());
  }

  /**
   * Display custom exception page.
   * 
   * @param AppException|Exception $exception The current exception thrown.
   * 
   * @return int Return status code for error.
  */
  private static function display(AppException|Exception $exception): int 
  {
    include_once path('views') . 'system_errors' . DIRECTORY_SEPARATOR . 'cli.php';

    return STATUS_ERROR;
  }
}