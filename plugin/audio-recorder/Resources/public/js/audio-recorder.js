"use strict";

import * as VolumeMeter from './libs/volume-meter';

const isFirefox = !!navigator.mediaDevices.getUserMedia;

const isDebug = false;
if (isDebug) {
  console.log(isFirefox ? 'firefox' : 'chrome');
}

let recorder;
let tempRecordedBlobs; // array of chunked audio blobs

let audioContext = new window.AudioContext();
let audioInput = null,
  realAudioInput = null,
  inputPoint = null;
let rafID = null;
let analyserContext = null;
let analyserNode = null;
let canvasWidth, canvasHeight;
let gradient;
let meter;

let aid = 0; // audio array current recording index
let aBlobs = []; // collection of audio blobs
let audios = []; // collection of audio objects for playing recorded audios

let maxTry;
let maxTime;
let nbTry = 0;
let nbTryLabelBase = ' - ' + Translator.trans('nb_try_label', {}, 'innova_audio_recorder');
let nbTryLabel = '';
let currentTime = 0;
let intervalID;
// avoid the recorded file to be chunked by setting a slight timeout
const recordEndTimeOut = 512;

const constraints = {
  audio: true
};

// store stream chunks every 10 ms
function handleDataAvailable(event) {
  if (event.data && event.data.size > 0) {
    tempRecordedBlobs.push(event.data);
  }
}

$('.modal').on('shown.bs.modal', function() {
  console.log('modal shown');
  // file name check and change
  $("#resource-name-input").on("change paste keyup", function() {
    if ($(this).val() === '') { // name is blank
      $(this).attr('placeholder', 'provide a name for the resource');
      $('#submitButton').prop('disabled', true);
    } else if ($('input:checked').length > 0) { // name is set and a recording is selected
      $('#submitButton').prop('disabled', false);
    }
    // remove blanks
    $(this).val(function(i, val) {
      return val.replace(' ', '_');
    });
  });

  $('#audio-record-start').on('click', recordStream);
  $('#audio-record-stop').on('click', stopRecording);
  $('#btn-audio-download').on('click', download);
  $('#submitButton').on('click', uploadAudio);

  maxTry = parseInt($('#maxTry').val());
  maxTime = parseInt($('#maxTime').val());

  currentTime = maxTime;

  if (maxTry > 0) {
    nbTryLabel = nbTryLabelBase + ' ' + nbTry.toString() + '/' + maxTry.toString();
    $('.nb-try').text(nbTryLabel);
  }

  if(maxTime > 0){
    $('.timer').text(' - ' + (maxTime).toString() + 's');
  }

});

$('body').on('click', '.play', function() {
  playAudio(this);
});
$('body').on('click', '.stop', function() {
  stopAudio(this);
});
$('body').on('click', '.delete', function() {
  deleteAudio(this);
});
$('body').on('click', 'input[name="audio-selected"]', function() {
  audioSelected(this);
});

$('.modal').on('hide.bs.modal', function() {
  console.log('modal closed');
  resetData();
});

// getUserMedia() polyfill
// see here https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia
const promisifiedOldGUM = function(constraints, successCallback, errorCallback) {

  // First get ahold of getUserMedia, if present
  let getUserMedia = (navigator.getUserMedia ||
    navigator.webkitGetUserMedia ||
    navigator.mozGetUserMedia);

  // Some browsers just don't implement it - return a rejected promise with an error
  // to keep a consistent interface
  if (!getUserMedia) {
    return Promise.reject(new Error('getUserMedia is not implemented in this browser'));
  }

  // Otherwise, wrap the call to the old navigator.getUserMedia with a Promise
  return new Promise(function(successCallback, errorCallback) {
    getUserMedia.call(navigator, constraints, successCallback, errorCallback);
  });

}

// Older browsers might not implement mediaDevices at all, so we set an empty object first
if (navigator.mediaDevices === undefined) {
  navigator.mediaDevices = {};
}


// Some browsers partially implement mediaDevices. We can't just assign an object
// with getUserMedia as it would overwrite existing properties.
// Here, we will just add the getUserMedia property if it's missing.
if (navigator.mediaDevices.getUserMedia === undefined) {
  navigator.mediaDevices.getUserMedia = promisifiedOldGUM;
}

navigator.mediaDevices.getUserMedia(constraints)
  .then(
    gumSuccess
  ).catch(
    gumError
  );

// getUserMedia Success Callback
function gumSuccess(stream) {
  if (isDebug) {
    console.log('success');
    console.log('getUserMedia() got stream: ', stream);
  }
  window.stream = stream;
  //recordStream();
  createVolumeMeter();
}

// getUserMedia Error Callback
function gumError(error) {
  const msg = 'navigator.getUserMedia error.';
  showError(msg, false);
  if (isDebug) {
    console.log(msg, error);
  }
}

function recordStream() {
  $('#audio-record-start').prop('disabled', 'disabled');
  $('#audio-record-stop').prop('disabled', '');

  tempRecordedBlobs = [];
  try {
    recorder = new MediaRecorder(window.stream);
  } catch (e) {
    const msg = 'Unable to create MediaRecorder with options Object.';
    showError(msg, false);
    if (isDebug) {
      console.log(msg, e);
    }
  }

  recorder.ondataavailable = handleDataAvailable;
  recorder.start(10); // collect 10ms of data
  if(maxTime > 0){
    intervalID = window.setInterval(function(){
      currentTime -= 1;
      $('.timer').text(' - ' + currentTime.toString() + 's');
      if(currentTime === 0){
        window.clearInterval(intervalID);
        stopRecording();
      }
    }, 1000);
  }

  nbTry++;
  if (maxTry > 0) {
    nbTryLabel = nbTryLabelBase + ' ' + nbTry.toString() + '/' + maxTry.toString();
    $('.nb-try').text(nbTryLabel);
  }

  if (isDebug) {
    console.log('MediaRecorder started', recorder);
  }
}

function stopRecording() {

  if (maxTry === 0 || nbTry < maxTry) {
    $('#audio-record-start').prop('disabled', '');
  }

  if(maxTime > 0){
    currentTime = maxTime;
    $('.timer').text(' - ' + currentTime.toString() + 's');
  }

  window.clearInterval(intervalID);

  // avoid recorded audio truncated end by setting a timeout
  window.setTimeout(function() {

    recorder.stop();
    $('#audio-record-stop').prop('disabled', 'disabled');

    if (isDebug) {
      console.log(tempRecordedBlobs);
    }
    let options = isFirefox ? 'audio/ogg; codecs=opus' : 'audio/wav';

    let superBuffer = new Blob(tempRecordedBlobs, {
      'type': options
    });

    let audioObject = new Audio();
    audioObject.src = window.URL ? window.URL.createObjectURL(superBuffer) : superBuffer;
    audios.push(audioObject);
    aBlobs.push(superBuffer);

    let html = '<div class="row recorded-audio-row" id="recorded-audio-row-' + aid.toString() + '" data-index="' + aid + '">';
    html += '       <div class="col-md-8">';
    html += '         <div class="btn-group">';
    html += '           <button type="button" role="button" class="btn btn-default fa fa-play play"></button>';
    html += '           <button type="button" role="button" class="btn btn-default fa fa-stop stop"></button>';
    html += '           <button type="button" role="button" class="btn btn-danger fa fa-trash delete"></button>';
    html += '         </div>';
    html += '       </div>';
    html += '       <div class="col-md-4">';
    html += '         <input type="radio" name="audio-selected" class="select">';
    html += '       </div>';
    html += '       <hr/>';
    html += '   </div>';
    $('#audio-records-container').append(html);
    aid++;
  }, recordEndTimeOut);
}

function showError(msg, canDownload = false) {

  $('#form-error-msg').text(msg);
  $('#form-error-msg-row').show();
  // allow user to save the recorded file on his device...
  if (canDownload) {
    $('#form-error-download-msg').show();
    $('#btn-video-download').show();
  }
  // change form view
  $('#form-content').hide();
  $('#submitButton').hide();
}

function audioSelected(elem) {
  $('#submitButton').prop('disabled', false);
}

function resetData() {

  cancelAnalyserUpdates();
  if (window.stream) {
    window.stream.getAudioTracks().forEach(function(track) {
      track.stop();
    });
  }

  audios = [];
  aBlobs = [];
  tempRecordedBlobs = null;
  recorder = null;
  audioContext = null;
  audioInput = null;
  realAudioInput = null;
  inputPoint = null;
  rafID = null;
  analyserContext = null;
  analyserNode = null;
  aid = 0;
}

function playAudio(elem) {
  const index = $(elem).closest('.recorded-audio-row').attr('data-index');
  audios[index].play();
}

function stopAudio(elem) {
  const index = $(elem).closest('.recorded-audio-row').attr('data-index');
  audios[index].pause();
  audios[index].currentTime = 0;
}

function deleteAudio(elem) {
  const index = $(elem).closest('.recorded-audio-row').attr('data-index');
  audios.splice(index, 1);
  aBlobs.splice(index, 1);

  $('#recorded-audio-row-' + index.toString()).remove();
  const noAudioSelected = $('input:checked').length === 0;
  if (audios.length === 0 || noAudioSelected) {
    $('#submitButton').prop('disabled', true);
  }

  // rebuilt all row id(s) and index
  $('.recorded-audio-row').each(function(i) {
    $(this).attr('id', 'recorded-audio-row-' + i.toString());
    $(this).attr('data-index', i);
  });

  aid = audios.length;

  nbTry--;
  if (maxTry > 0) {
    nbTryLabel = nbTryLabelBase + ' ' + nbTry.toString() + '/' + maxTry.toString();
    $('.nb-try').text(nbTryLabel);
    if (nbTry < maxTry) {
      $('#audio-record-start').prop('disabled', '');
    }
  }
}


// use with claro new Resource API
function uploadAudio() {
  // get selected audio index
  let index = -1;
  index = $('input:checked').closest('.recorded-audio-row').attr('data-index');
  if (index > -1) {
    let blob = aBlobs[index];
    let formData = new FormData();
    // nav should be mandatory
    if (isFirefox) {
      formData.append('nav', 'firefox');
    } else {
      formData.append('nav', 'chrome');
    }
    // convert is optionnal
    formData.append('convert', true);
    // file is mandatory
    formData.append('file', blob);
    // filename is mandatory
    let fileName = $("#resource-name-input").val();
    formData.append('fileName', fileName);

    let route = $('#arForm').attr('action');
    xhr(route, formData, null, function(fileURL) {});
  }
}

function xhr(url, data, progress, callback) {

  const message = Translator.trans('creating_resource', {}, 'innova_audio_recorder');
  // tell the user that his action has been taken into account
  $('#submitButton').text(message);
  $('#submitButton').attr('disabled', true);
  $('#submitButton').append('&nbsp;<i id="spinner" class="fa fa-spinner fa-spin"></i>');

  let request = new XMLHttpRequest();
  request.onreadystatechange = function() {
    if (request.readyState === 4 && request.status === 200) {
      if(isDebug) console.log('xhr end with success');
      resetData();

      // use reload or generate route...
      location.reload();

    } else if (request.status === 500) {
      if(isDebug) console.log('xhr error');
      //var errorMessage = Translator.trans('resource_creation_error', {}, 'innova_audio_recorder');
      //$('#form-error-msg').text(errorMessage);
      $('#form-error-msg-row').show();
      // allow user to save the recorded file on his device...
      let index = -1;
      index = $('input:checked').closest('.recorded-audio-row').attr('data-index');
      if (index > -1) {
        // show download button
        $('#btn-audio-download').show();
        $('#form-content').hide();
        $('#submitButton').hide();
      }
    }
  };

  request.upload.onprogress = function(e) {
    // if we want to use progress bar
  };

  request.open('POST', url, true);
  request.send(data);

}

function download() {
  const index = $('input:checked').closest('.recorded-audio-row').attr('data-index');
  let blob = aBlobs[index];
  const url = window.URL.createObjectURL(blob);
  let a = document.createElement('a');
  a.style.display = 'none';
  a.href = url;

  let fileName = $("#resource-name-input").val();
  a.download = fileName + '.webm';
  document.body.appendChild(a);
  a.click();
  setTimeout(function() {
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  }, 100);
}

function createVolumeMeter() {
  inputPoint = audioContext.createGain();
  // Create an AudioNode from the stream.
  realAudioInput = audioContext.createMediaStreamSource(window.stream);

  meter = VolumeMeter.createAudioMeter(audioContext);
  realAudioInput.connect(meter);
  draw();
}

function draw(time) {

  if (!analyserContext) {
    let canvas = document.getElementById("analyser");
    canvasWidth = canvas.width;
    canvasHeight = canvas.height;
    analyserContext = canvas.getContext('2d');
    gradient = analyserContext.createLinearGradient(0, 0, canvasWidth, 0);
    gradient.addColorStop(0.15, '#ffff00'); // min level color
    gradient.addColorStop(0.80, '#ff0000'); // max level color
  }

  // clear the background
  analyserContext.clearRect(0, 0, canvasWidth, canvasHeight);

  analyserContext.fillStyle = gradient;
  // draw a bar based on the current volume
  analyserContext.fillRect(0, 0, meter.volume * canvasWidth * 1.4, canvasHeight);

  // set up the next visual callback
  rafID = window.requestAnimationFrame(draw);
}

function cancelAnalyserUpdates() {
  window.cancelAnimationFrame(rafID);
  // clear the current state
  if (analyserContext) {
    analyserContext.clearRect(0, 0, canvasWidth, canvasHeight);
  }
  rafID = null;
}
