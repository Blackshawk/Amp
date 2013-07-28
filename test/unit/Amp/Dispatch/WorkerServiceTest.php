<?php

use Amp\Dispatch\WorkerService,
    Amp\Dispatch\FrameParser,
    Amp\Dispatch\FrameWriter,
    Amp\Dispatch\Frame,
    Amp\Dispatch\BinaryDispatcher,
    Amp\Dispatch\ResourceException;

class WorkerServiceTest extends PHPUnit_Framework_TestCase {
    
    function testOnReadable() {
        
        $callId = pack('N', 123456);
        
        $len = 256;
        $workload = serialize([str_repeat('.', $len)]);
        $procedure = 'strlen';
        $procLen = chr(strlen($procedure));
        $payload = $callId . chr(BinaryDispatcher::CALL) . $procLen . $procedure . $workload;
        $callFrame = new Frame($fin = 1, $rsv = 0, $opcode = Frame::OP_DATA, $payload);
        
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $callFrame);
        rewind($inputStream);
        
        $outputStream = fopen('php://memory', 'r+');
        $parser = new FrameParser($inputStream);
        $writer = new FrameWriter($outputStream);
        
        $workerService = new WorkerService($parser, $writer);
        $workerService->onReadable();
        
        rewind($outputStream);
        
        $endParser = new FrameParser($outputStream);
        $frameArr = $endParser->parse();
        list($isFin, $rsv, $opcode, $payload) = $frameArr;
        
        $this->assertEquals($callId, substr($payload, 0, 4));
        $this->assertEquals($len, unserialize(substr($payload, 5)));
        $this->assertEquals(ord($payload[4]), BinaryDispatcher::CALL_RESULT);
    }
    
    function testOnReadableReturnsCallErrorOnInvalidProcedureReturnType() {
        
        $callId = pack('N', 123456);
        
        $procedure = 'invalidReturnTestFunc';
        $procLen = chr(strlen($procedure));
        $payload = $callId . chr(BinaryDispatcher::CALL) . $procLen . $procedure;
        $callFrame = new Frame($fin = 1, $rsv = 0, $opcode = Frame::OP_DATA, $payload);
        
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $callFrame);
        rewind($inputStream);
        
        $outputStream = fopen('php://memory', 'r+');
        $parser = new FrameParser($inputStream);
        $writer = new FrameWriter($outputStream);
        
        $workerService = new WorkerService($parser, $writer);
        $workerService->onReadable();
        
        rewind($outputStream);
        
        $endParser = new FrameParser($outputStream);
        $frameArr = $endParser->parse();
        list($isFin, $rsv, $opcode, $payload) = $frameArr;
        
        $this->assertEquals($callId, substr($payload, 0, 4));
        $this->assertEquals(ord($payload[4]), BinaryDispatcher::CALL_ERROR);
    }
    
    /**
     * @expectedException Amp\Dispatch\ResourceException
     */
    function testOnReadableThrowsExceptionOnBrokenWritePipe() {
        $callId = pack('N', 123456);
        
        $procedure = 'strlen';
        $workload = serialize('test');
        $procLen = chr(strlen($procedure));
        $payload = $callId . chr(BinaryDispatcher::CALL) . $procLen . $procedure . $workload;
        $callFrame = new Frame($fin = 1, $rsv = 0, $opcode = Frame::OP_DATA, $payload);
        
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $callFrame);
        rewind($inputStream);
        
        $parser = new FrameParser($inputStream);
        $writer = $this->getMock('Amp\Dispatch\FrameWriter', NULL, ['writeAll']);
        $writer->expects($this->any())
               ->method('writeAll')
               ->will($this->throwException(new ResourceException));
        
        $workerService = new WorkerService($parser, $writer);
        $workerService->onReadable();
    }
    
}

function invalidReturnTestFunc() {
    return ["procedure return must be NULL, scalar or Iterator"];
}

function streamWorkerResultFunc() {
    return new MyTestIterator;
}

class MyTestIterator implements Iterator {
    
    private $position = 0;
    private $parts = ['chunk1', 'chunk2', 'chunk3'];

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->parts[$this->position];
    }

    function key() {
        return $this->position;
    }

    function next() {
        $this->position++;
    }

    function valid() {
        return isset($this->parts[$this->position]);
    }
    
}