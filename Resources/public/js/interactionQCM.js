//cf contenu_container_QCM
var container = $('div#ujm_exobundle_interactionqcmtype_choices'); // Div which contain the dataprototype
var tableChoices = $('#tableChoice'); // div which contain the choices array

var typeQCM;

// QCM Creation
function creationQCM(expectedAnswer, response, point, comment, positionForce, addchoice, deleteChoice,edition, tQCM) {
    
    $("#ujm_exobundle_interactionqcmtype_typeQCM_2").prop("checked", true);
    $("#ujm_exobundle_interactionqcmtype_weightResponse").prop("checked", true);
    $('.radio').css({"display" : "inline-block", "margin-right" : "4%", "margin-top" : "1%"});

    //$('ujm_exobundle_interactionqcmtype_typeQCM_1').css({});
    var index; // number of choices
    
    typeQCM = JSON.parse(tQCM);

    tableChoicesCreation(expectedAnswer, response, point, comment, positionForce, addchoice, deleteChoice,edition, 0);

    // Number of choice initially
    index = container.find(':input').length;

    // If no choice exist, add two choices by default
    if (index == 0) {
        addChoice(container, deleteChoice,comment,edition);
        $('#newTable').find('tbody').append('<tr></tr>');
        addChoice(container, deleteChoice,comment,edition);
    // If choice already exist, add button to delete it
    } else {
        tableChoices.children('tr').each(function() {
            addDelete($(this), deleteChoice);
        });
    }

    whichChange();

    // when select a radio box, deselect the other because can only have one selected
    $(document).on('click', ':radio', function () {
        if ($(this).is(':checked')) {
           radioJustChecked = $(this).attr("id");
           $('#newTable').find(('tr:not(:first)')).each(function () {
                if (radioJustChecked != $(this).find('input').eq(1).attr("id")) {
                    $(this).find('input').eq(1).removeAttr('checked');
                }
           });
       }
    });
    // Make the choices' table sortable with jquery ui plugin
    //$('tbody').sortable();

    // Return a helper with preserved width of cells
    var fixHelper = function(e, ui) {
        ui.children().each(function() {
                $(this).width($(this).width());
        });
        return ui;
    };
    /*$('tbody').sortable({
        helper: fixHelper,
        cancel: 'contenteditable',
        stop: function (event, ui) {
            $(ui.item).find('.claroline-tiny-mce').each(function () {
                tinyMCE.get($(this).attr('id')).remove();
                $(this).removeClass('tiny-mce-done');
                $('body').trigger('DOMSubtreeModified');
            });
        }
    });*/
   // $("#ujm_exobundle_interactionqcmtype_choices_1_label").parent().append('<span class="input-group-addon" id="basic-addon2"><a href="#" style="background-color="#FFFFFF";><i class="fa fa-font"></i></a></span>');
}

// QCM Edition
function creationQCMEdit(expectedAnswer, response, point, comment, positionForce, addchoice, deleteChoice, tQCM,edition, nbResponses) {

    var index = 0;
    
    typeQCM = JSON.parse(tQCM);

    tableChoicesCreation(expectedAnswer, response, point, comment, positionForce, addchoice, deleteChoice,edition, nbResponses);

    // Get the form field to fill rows of the choices' table
    container.children().first().children('div').each(function () {
     
        // Add a row to the table
        $('#newTable').find('tbody').append('<tr></tr>');
           
         $(this).find('.row').each(function () {

            fillChoicesArray($(this),edition,index);    
          
            // Add the form errors
            $('#choiceError').append($(this).find('.field-error'));
        });

        if (nbResponses == 0) {
            // Add the delete button
            $('#newTable').find('tr:last').append('<td class="classic"></td>');
            addDelete($('#newTable').find('td:last'), deleteChoice); 
          
        }
        
        $('#ujm_exobundle_interactionqcmtype_choices_'+index+'_weight').click(function() {
            $(this).focus();
        });
        
        index++;
        
    });
    var i=0; //index of  button
    //Enables advanced edition QCM - Matching
    $('.classic').find('textarea').each(function() {
        //if there is at the start an open tag and a close at the end. And at the middle all caracters possible or nothing
        if($(this).val().match("<.+>.+|\s<\/.+>$")) {
            idProposalVal = $(this).attr("id");
            $("#"+idProposalVal).addClass("claroline-tiny-mce hide");
            $("#"+idProposalVal).data("data-theme","advanced");
            $("#btnEditionLabel_"+i).remove();
        }
        i++;
    });

    // Remove the useless fields form
    container.remove();
    tableChoices.next().remove();

    whichChecked();
    whichChange();

    $(document).on('click', ':radio', function () {
        if ($(this).is(':checked')) {
           radioJustChecked = $(this).attr("id");
           $('#newTable').find(('tr:not(:first)')).each(function () {
                if (radioJustChecked != $(this).find('input').eq(1).attr("id")) {
                    $(this).find('input').eq(1).removeAttr('checked');
                }
           });
       }
    });

    // Make the choices' table sortable with jquery ui plugin
    //$('tbody').sortable();

    // Return a helper with preserved width of cells
    var fixHelper = function(e, ui) {
        ui.children().each(function() {
                $(this).width($(this).width());
        });
        return ui;
    };
    
//    $('tbody').sortable({
//        helper: fixHelper,
////     cancel: 'contenteditable',
//        stop: function (event, ui) {
//            $(ui.item).find('.claroline-tiny-mce').each(function () {
//                tinyMCE.get($(this).attr('id')).remove();
//                $(this).removeClass('tiny-mce-done');
//                $('body').trigger('DOMSubtreeModified');
//            });
//        }
//    });
}

// Add a choice
function addChoice(container, deleteChoice, comment, edition) {
    var uniqChoiceID = false;

    var index = $('#newTable').find('tr:not(:first)').length;

    while (uniqChoiceID == false) {
        if ($('#ujm_exobundle_interactionqcmtype_choices_' + index+'_label').length) {
            index++;
        } else {
            uniqChoiceID = true;
        }
    }

    // change the "name" by the index and delete the symfony delete form button
    var contain = $(container.attr('data-prototype').replace(/__name__label__/g, 'Choice n°' + (index))
        .replace(/__name__/g, index)
        .replace('<a class="btn btn-danger remove" href="#">Delete</a>', '')
    );
    // Add the button to delete a choice
    addDelete(contain, deleteChoice);
    // Add the modified dataprototype to the page
    container.append(contain);
     
    // Get the form field to fill rows of the choices' table
    container.find('.row').each(function () {
        fillChoicesArray($(this),edition,index);
    });

    // Add the delete button
    $('#newTable').find('tr:last').append('<td class="classic"></td>');
    $('#newTable').find('td:last').append(contain.find('a.btn-danger'));
    // Remove the useless fileds form
    container.remove();
    tableChoices.next().remove();

    whichChecked();
    
    $('#ujm_exobundle_interactionqcmtype_choices_'+index+'_weight').click(function() {
        $(this).focus();
    });
}

// Check if form is valid
function check_form(nbrChoices, answerCoched, labelEmpty, pointAnswers, pointAnswer, inviteQuestion) {
    //"use strict";

    /*if ($("*[id$='_penalty']").length > 0) {
        $("*[id$='_penalty']").val($("*[id$='_penalty']").val().replace(/[-]/, ''));
    }*/

    // If no question is asked
    if ($('#ujm_exobundle_interactionqcmtype_interaction_invite').val() == '') {
        alert(inviteQuestion);
        return false;
    } else {
        // If there is no at least two choices
        if (($('#newTable').find('tr:not(:first)').length) < 2) {
            alert(nbrChoices);
            return false;
        } else {
            // If no expected answer is selected
            var nbr_rep_coched = 0;
            $('#newTable').find('tr:not(:first)').each(function (index) {
                if ($(this).find('td').eq(1).find('input').is(':checked')) {
                    nbr_rep_coched = nbr_rep_coched + 1;
                }
            });
            if (nbr_rep_coched === 0) {
                alert(answerCoched);
                return false;
            } else {
                // If all the points fields are fill
                if ($('#ujm_exobundle_interactionqcmtype_weightResponse').is(':checked')) {
                    var checked = true;
                    $('#newTable').find('tr:not(:first)').each(function (index) {
                        if ($(this).find('td').eq(4).find('input').val() == '') {
                            checked = false;
                            return false;
                        }
                    });

                    if (checked == false) {
                        alert(pointAnswers);
                        return false;
                    }
                }
            }
        }
    }
}

// Set the choices order
function setOrder() {

    var order = 1;

    $('#newTable').find('tr:not(:first)').each(function () {
        $(this).find('input:first').val(order);
        order++;
    });
}

function whichChecked() {
     // Show or hide positionForce if shuffle is checked
    if ($('#ujm_exobundle_interactionqcmtype_shuffle').is(':checked')) {
        tableChoices.find('th').eq(4).show();
        $("*[id$='_positionForce']").each(function () {
            $(this).parent('td').show();
        });
    } else {
        tableChoices.find('th').eq(4).hide();
        $("*[id$='_positionForce']").each(function () {
           $(this).parent('td').hide();
       });
    }
    // Disable or not the score by response if weightResponse is checked
    if ($('#ujm_exobundle_interactionqcmtype_weightResponse').is(':checked')) {
        $('#ujm_exobundle_interactionqcmtype_scoreRightResponse').css({"display" : "none"});
        $('#ujm_exobundle_interactionqcmtype_scoreFalseResponse').css({"display" : "none"});
        $('#labelRightResponse').css({"display" : "none"});
        $('#labelFalseResponse').css({"display" : "none"});
         tableChoices.find('th').eq(2).show();
        $("*[id$='_weight']").each(function() {
           // $(this).css({"display" : "inline-block"});    
             $(this).parent('td').show();
        });
    } else {
        $('#ujm_exobundle_interactionqcmtype_scoreRightResponse').css({"display" : "inline-block"});
        $('#ujm_exobundle_interactionqcmtype_scoreFalseResponse').css({"display" : "inline-block"});
        $('#labelRightResponse').css({"display" : "inline-block"});
        $('#labelFalseResponse').css({"display" : "inline-block"});
        tableChoices.find('th').eq(2).hide();
        $("*[id$='_weight']").each(function() {
           // $(this).css({"display" : "none"});
             $(this).parent('td').hide();
        });
    }

    // Change the type od ExpectedAnswer (radio or checkbox) depending on which typeQCM is choose
    var type = $('#ujm_exobundle_interactionqcmtype_typeQCM :checked').val();

    $("*[id$='_rightResponse']").each(function () {
        if (typeQCM[type] == 1) {
            $(this).prop('type', 'checkbox');
        } else {
            $(this).prop('type', 'radio');
        }
    });
}

function whichChange() {
    // When "choices shuffle" change, show position force possibility
    $('#ujm_exobundle_interactionqcmtype_shuffle').change(function () {
        if ($(this).is(':checked')) {
            tableChoices.find('th').eq(4).show();
            $("*[id$='_positionForce']").each(function () {
                $(this).parent('td').show();
            });
        } else {
            tableChoices.find('th').eq(4).hide();
            $("*[id$='_positionForce']").each(function () {
               $(this).parent('td').hide();
           });
        }
    });

    // When "set points by response" change, disable or not single choice point
    $('#ujm_exobundle_interactionqcmtype_weightResponse').change(function () {
        if ($(this).is(':checked')) {
            $('#ujm_exobundle_interactionqcmtype_scoreRightResponse').css({"display" : "none"});
            $('#ujm_exobundle_interactionqcmtype_scoreFalseResponse').css({"display" : "none"});
            $('#labelRightResponse').css({"display" : "none"});
            $('#labelFalseResponse').css({"display" : "none"});
            tableChoices.find('th').eq(2).show();
            $("*[id$='_weight']").each(function() {
               // $(this).css({"display" : "inline-block"});
                $(this).parent('td').show();
            });
        } else {
            $('#ujm_exobundle_interactionqcmtype_scoreRightResponse').css({"display" : "inline-block"});
            $('#ujm_exobundle_interactionqcmtype_scoreFalseResponse').css({"display" : "inline-block"});
            $('#labelRightResponse').css({"display" : "inline-block"});
            $('#labelFalseResponse').css({"display" : "inline-block"});
            tableChoices.find('th').eq(2).hide();
            $("*[id$='_weight']").each(function() {
              //  $(this).css({"display" : "none"});
                $(this).parent('td').hide();
            });
        }
    });

    // When "type of QCM (unique/multiple)" change, change the expected response to radio or checkbox
    $('#ujm_exobundle_interactionqcmtype_typeQCM').change(function () {
        var type = $('#ujm_exobundle_interactionqcmtype_typeQCM :checked').val();

        $("*[id$='_rightResponse']").each(function () {
            if (typeQCM[type] == 1) {
                $(this).prop('type', 'checkbox');
            } else {
                $(this).prop('type', 'radio');
                $(this).attr('checked', false);
            }
        });
    });
}

function fillChoicesArray(row,edition,index) {   
    // Add the field of type input
    if (row.find('input').length) {
        if (row.find('input').attr('id').indexOf('ordre') == -1) {
            $('#newTable').find('tr:last').append('<td class="classic"></td>');
            $('#newTable').find('td:last').append(row.find('input'));
        
        } else {
            // Add the field positionForced as hidden td
            $('#newTable').find('tr:last').append('<td class="classic" style="display:none;"></td>');
            $('#newTable').find('td:last').append(row.find('input'));
      
        }
    }
    
    //Add the field of type textarea answer 
    if (row.find('*[id$="_label"]').length) { 
        $('#newTable').find('tr:last').append('<td class="classic"><span id="spanLabel_'+index+'" class="input-group"></span></td>');
        $('#spanLabel_'+index).append(row.find('*[id$="_label"]'));
        $('#spanLabel_'+index).append('<span class="input-group-btn"><a class="btn btn-default" id="btnEditionLabel_'+index+'" onClick="advancedEdition(\'ujm_exobundle_interactionqcmtype_choices_'+index+'_label\',\'btnEditionLabel_'+index+'\',event);" title="'+edition+'"><i class="fa fa-font"></i></a></span>');
   }
    
    //Add the field of type textarea feedback
    if (row.find('*[id$="_feedback"]').length) { 
       //Ajoute une cellule au tableau avec un bouton commentaire
       $('#newTable').find('tr:last').append('<td class="classic"><a class="btn btn-default" id="btnHiddenFeedback_'+index+'" onClick="addTextareaFeedback(\'spanFeedback_'+index+'\',\'btnHiddenFeedback_'+index+'\')" ><i class="fa fa-comments-o"></i></a><span id="spanFeedback_'+index+'" class="input-group" style="display:none;"></span></td>');
       //Ajoute en cacher le textarea et son bouton d'edition avancée
       $('#spanFeedback_'+index).append(row.find('*[id$="_feedback"]'));
       $('#spanFeedback_'+index).append('<span class="input-group-btn"><a class="btn btn-default" id="btnEditionFeedback_'+index+'" onClick="advancedEdition(\'ujm_exobundle_interactionqcmtype_choices_'+index+'_feedback\',\'btnEditionFeedback_'+index+'\',event);" title="'+edition+'"><i class="fa fa-font"></i></a></span>');
    }   
}

function tableChoicesCreation(expectedAnswer, response, point, comment, positionForce, addchoice, deleteChoice, edition, nbResponses) {

    if (nbResponses == 0) {
        // Add the structure od the table
        tableChoices.append('<table id="newTable" class="table table-striped table-condensed"><thead><tr style="background-color: lightsteelblue;"><th class="classic">'+expectedAnswer+'</th><th class="classic">'+response+'</th><th class="classic">'+point+'</th><th class="classic">'+comment+'</th><th class="classic">'+positionForce+'</th><th class="classic">'+deleteChoice+'</th></tr></thead><tbody><tr></tr></tbody></table>');
        // create the button to add a choice
        var add = $('<a title="'+addchoice+'" href="#" id="add_choice" class="btn btn-default"><i class="fa fa-plus"></i></a>');

        // Add the button after the table
        tableChoices.append(add);
        
        // When click, add a new choice in the table
        add.click(function (e) {
            $('#newTable').find('tbody').append("<tr></tr>");
            addChoice(container, deleteChoice, comment, edition);      
            e.preventDefault(); // prevent add # in the url
            return false;
        });
    } else {
        // Add the structure od the table
        tableChoices.append('<table id="newTable" class="table table-striped table-bordered table-condensed"><thead><tr style="background-color: lightsteelblue;"><th class="classic">'+expectedAnswer+'</th><th class="classic">'+response+'</th><th class="classic">'+point+'</th><th class="classic">'+comment+'</th><th class="classic">'+positionForce+'</th></tr></thead><tbody><tr></tr></tbody></table>');
    }
}
function addTextareaFeedback(spanFeedback,btnHiddenFeedback){
     $('#'+btnHiddenFeedback+'').remove();
     $('#'+spanFeedback+'').removeAttr( 'style' );    
}