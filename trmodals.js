/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu> 
 *           Nate Robinson <nate@psc.edu>
 *           Bryan Learn <blearn@psc.edu>
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

//hardcoded modal IDs (not the best approach)
modalID = {'warn': "#warnModal", 'error': "#errorModal", 'success': "#successModal"};
modalText = {'warn': "#warnModalText", 'error': "#errorModalText", 'success': "#successModalText"};

var formSrc = "";

/* display the modal window */
function modalMessage(type, messageBody){
    $(modalText[type]).html(messageBody);
    $(modalID[type]).modal('show');
}

function genericModalWarning(msg) {
	modalMessage('error', msg);
}

function userManagementFormInfo(flag, msg) {
    if (flag == 0) {
	modalMessage('success', msg);
    }
    if (flag == 1) {
	modalMessage('error', msg);
    }
}

function customersFormInfo(flag, msg) {
    if (flag == 0) {
	modalMessage('success', msg);
    }
    if (flag == 1) {
	modalMessage('error', msg);
    }
}

function addCustomerFormInfo(flag, msg) {
    if (flag == 0) {
	modalMessage('success', msg);
    }
    if (flag == 1) {
	modalMessage('error', msg);
    }
}

function accountMgmtFormInfo(flag, msg) {
    if (flag == 0) {
	modalMessage('success', msg);
    }
    if (flag == 1) {
	modalMessage('error', msg);
    }
}

function routesFormInfo(flag, msg) {
    if (flag == 0) {
	modalMessage('success', msg);
    }
    if (flag == 1) {
	modalMessage('error', msg);
    }
}

/* when the users closes the modal window we want to go to the
 * next appropriate page. so we find the appropriate button
 * for that modal and change the button from it's default to 
 * replacing the current window/url with the target.  
 * the URL is set by the exception handlers on the main page
*/
function newUserFormInfo(flag, msg, url) {
    var myBtn;
    if (flag == 0) {
	myBtn = document.getElementById('success-close');
	myBtn.addEventListener('click', function(event) {
	    window.location.replace(url);
	});
	modalMessage('success', msg);
    }
    if (flag == 1) {
	myBtn = document.getElementById('error-close');
	myBtn.addEventListener('click', function(event) {
	    window.location.replace(url);
	});
	modalMessage('error', msg);
    }
}

/* same comment as the previous function (newUserFormInfo) */
function changePassFormInfo(flag, msg, url) {
    var myBtn;
    if (flag == 0) {
	myBtn = document.getElementById('success-close');
	myBtn.addEventListener('click', function(event) {
	    window.location.replace(url);
	});
	modalMessage('success', msg);
    }
    if (flag == 1) {
	/* in this case an error just redisplays the same page */
	/* so no need to do a location.replace() */
	modalMessage('error', msg);
    }
}

function loginFormInfo(flag, msg){
    if(flag == 1){
        modalMessage('error', msg);
    }
}
