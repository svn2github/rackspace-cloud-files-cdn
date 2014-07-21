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

namespace OpenCloud\Volume\Resource;

use OpenCloud\Common\Exceptions;
use OpenCloud\Common\Resource\PersistentResource;

/**
 * The VolumeType class represents a single block storage volume type
 */
class VolumeType extends PersistentResource
{
    public $id;
    public $name;
    public $extra_specs;

    protected static $json_name = 'volume_type';
    protected static $url_resource = 'types';

    public function create($params = array())
    {
        return $this->noCreate();
    }

    public function update($params = array())
    {
        return $this->noUpdate();
    }

    public function delete()
    {
        return $this->noDelete();
    }
}
