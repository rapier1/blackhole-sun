/*
 * Copyright (c) 2017 The Board of Trustees of Carnegie Mellon University.
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

function modalSetFormSrc(value){
    formSrc = value;
}

function modalMessage(type, messageBody){
    $(modalText[type]).html(messageBody);
    $(modalID[type]).modal('show');
}

function isoFormInfo(flag, msg){
    if( formSrc == "isoForm" ){ //if data sent from this form
        if(flag == 1){
            modalMessage('error', msg);
        }
        else if(flag == 0){
            modalMessage('success', "ISO generation form submitted.");
        }
    }
}

function isoListInfo(flag, msg){
    if( formSrc == "isoList" ){ //if data sent from this form
        if(flag == 1){
            modalMessage('error', msg);
        }
        else if(flag == 0){
            modalMessage('success', "");
        }
    }   
}

function managementFormInfo(flag, msg) {
    if (formSrc == "management") {
	if (flag == 0) {
	    modalMessage('success', msg);
	}
	if (flag == 1) {
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

function signUpFormInfo(flag, msg){
    if( formSrc == "signUp" ){ //if data sent from this form
        if(flag == 1){
            modalMessage('error', msg);
        }
        else if(flag == 0){
            var notifyStr = "Your subscription request to TestRig2.0 has been received. Please allow 1 ";
            notifyStr += "business day to process the request and receive approval notification.";
            notifyStr += "Your SSH public key will be mailed to the supplied contact address shortly.";
            modalMessage('success', notifyStr);
        }
    }
    
}
