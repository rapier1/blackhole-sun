<?php
/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. *
 */

// change these to the appropriate values for your mysql installation
define ('DB_HOST', 'xxxx'); // where your sql install lives
define ('DB_USERNAME', 'xxxx'); // username for the database - not root!
define ('DB_PASSWORD','xxxx'); // password for that user
define ('DB_NAME', 'xxxx'); // name of the blackholesun database
define ('DURATION_TIMER', 10); // minutes of inactivity
define ('EXASERVER_CLIENTSIDE', 'localhost'); // where the exabgp client interface lives
define ('EXASERVER_CLIENTPORT', '20202'); // Its port
define ('EXASERVER_CLIENTBUFSIZ', '64000');// how much data to read into the incoming buffer
define ('PRIVATE_SIGNATURE_KEY', '/usr/local/etc/blackhole_sun/keys/ui_rsa');
/* the above is the path to the private key used to digitally sign the messages between
 * the user interface and the client process. This should be a different key than the key pair
 * used to communicate between the client process and the exabpg server interface*/
define ('UI_UUID', '9e65408727d8a4c5a8a554385e1e1642');
/* the above is a unique identifier for the user interface. it is used by the client to
 * look up the correct public key corresponding to the UI key. This one was generated
 * with dbus-uuidgen but any uuid generator should work*/	  
?>
