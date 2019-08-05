/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu> 
 *          Nate Robinson <nate@psc.edu>
 *          Bryan Learn <blearn@psc.edu>
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

$("form").submit(function(e) {
    var ref = $(this).find("[monkey]");
    $(ref).each(function(){
        if ( $(this).val() == '' )
        {
            alert("Please enter information for all required fields.");
            $(this).focus();
            e.preventDefault();
            return false;
        }
    });  return true;
});

function toggle_vis(id) {
    var e = document.getElementById(id);
    if (e.style.display == 'block')
	e.style.display = 'none';
    else
	e.style.display = 'block';
}

/* we use this to set a value indicating which page we are coming from 
 * this ensures that the function called is appropriate for that page
 * honestly, I'm not sure about this but I inherited this code and
 * it's working at the moment
*/

function modalSetFormSrc(value){
    formSrc = value;
}

/* display the modal window */
function modalMessage(type, messageBody){
    $(modalText[type]).html(messageBody);
    $(modalID[type]).modal('show');
}

function userManagementFormInfo(flag, msg) {
    if (formSrc == "userManagement") {
	if (flag == 0) {
	    modalMessage('success', msg);
	}
	if (flag == 1) {
	    modalMessage('error', msg);
	}
    }
}

function customersFormInfo(flag, msg) {
    if (formSrc == "customers") {
	if (flag == 0) {
	    modalMessage('success', msg);
	}
	if (flag == 1) {
	    modalMessage('error', msg);
	}
    }
}

function addClientFormInfo(flag, msg) {
    if (formSrc == "addClient") {
	if (flag == 0) {
	    modalMessage('success', msg);
	}
	if (flag == 1) {
	    modalMessage('error', msg);
	}
    }
}

function accountMgmtFormInfo(flag, msg) {
    if (formSrc == "accountMgmt") {
	if (flag == 0) {
	    modalMessage('success', msg);
	}
	if (flag == 1) {
	    modalMessage('error', msg);
	}
    }
}

function mainpageFormInfo(flag, msg) {
    if (formSrc == "mainpage") {
	if (flag == 1) {
	    modalMessage('success', msg);
	}
	if (flag == -1) {
	    modalMessage('error', msg);
	}
    }
}

/* when the users closes the modal window we want to go to the
 * next appropriate page. so we find the appropriate button
 * for that modal and change the button from it's default to 
 * replacing the current window/url with the target.  
 * the URL is set by the exception handlers on the main page
*/
function newUserFormInfo(flag, msg, url) {
    if (formSrc == "newUser") {
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
}

/* same comment as the previous function (newUserFormInfo) */
function changePassFormInfo(flag, msg, url) {
    if (formSrc == "changePass") {
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
}

function adminFormInfo(flag, msg, type){
    if( formSrc == "admin" ){ //if data sent from this form
        if(flag == 1){
            modalMessage('error', msg);
	    if (type == 1)
		toggle_vis('ud');
	    if (type == 2)
		toggle_vis('np');
	    if (type == 3)
		toggle_vis('nk');
        }
        else if(flag == 0){
	    if (type == 1)
		modalMessage('success', "Admin form submitted.");
	    if (type == 2)
		modalMessage('success', "Password successfully changed.");
	    if (type == 3)
		modalMessage('success', "New SCP generated and emailed to contact.");        
	}
    }
}

function loginFormInfo(flag, msg){
    if( formSrc == "login" ){ //if data sent from this form
        if(flag == 1){
            modalMessage('error', msg);
        }
        // no success message on login, just log user in.
    }
    
}
