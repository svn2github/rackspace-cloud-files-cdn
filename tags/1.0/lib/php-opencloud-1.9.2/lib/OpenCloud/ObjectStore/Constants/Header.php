<?php
/**
 * Copyright 2012-2014 Rackspace US, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace OpenCloud\ObjectStore\Constants;

/**
 * Constants for different request and metadata headers.
 */
class Header
{
    const OBJECT_COUNT = 'Object-Count';
    const BYTES_USED = 'Bytes-Used';
    const ACCESS_LOGS = 'Access-Log-Delivery';

    const TRANS_ID = 'Trans-Id';
    const ENABLED = 'Enabled';
    const LOG_RETENTION = 'Log-Retention';
} 
