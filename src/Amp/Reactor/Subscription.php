<?php

namespace Amp\Reactor;

interface Subscription {
    
    const DISABLED = 0;
    const ENABLED = 1;
    const CANCELLED = -1;
    
    function status();
    function cancel();
    function enable();
    function disable();
    
}

