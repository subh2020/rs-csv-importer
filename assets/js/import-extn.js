/* import-extn.js
 * Extention of Really Simply CSV Importer.
 * 1.0.0
 * 2016-10-07 18:25:19
 *
 * FavReadsIndia
 * License: Commercial
 *   
 */
var splitInfo = {};
var config = {};
var g_fs;
config.separator = isset(ajax_object.separator)?ajax_object.separator:$.csv.defaults.separator;
config.delimiter = isset(ajax_object.delimiter)?ajax_object.delimiter:$.csv.defaults.delimiter;
config.headers =   isset(ajax_object.hasHeaders)?ajax_object.hasHeaders:$.csv.defaults.headers;
config.MaxlinesPerPart = parseInt(ajax_object.MaxlinesPerPart);

var progressbar = $('#progressbar');
var value = 0;/*progressbar.val()*/;

function isset(variable) {
    return typeof variable !== typeof undefined ? true : false;
}

$(document).ajaxStart(function() {
  $("#loading").show();
});

$(document).ajaxStop(function() {
  $("#loading").hide();
});

function loading(val) 
{
	if(!Modernizr.meter){
		alert('Sorry your brower does not support HTML5 progress bar');
	} else {
		var new_val = parseInt(value) + parseInt(val);
		if (new_val > 100) {
			new_val = 100;			           
		}
		value = new_val;
		addValue = $('#progressbar').val(parseInt(value));
		
		$('.progress-value').html(parseInt(value) + '%');
	}
}


function uploadFile(file_name, csv_content, total_lineCount) 
{
	var fd = new FormData();
	fd.append("action", "upload_csv_files");
	fd.append("file_name", file_name);
	fd.append("file_data", JSON.stringify(csv_content));
	fd.append("security", ajax_object.ajax_nonce);
	
	var replace_by_title = $("input[name=replace-by-title]:checked").val();
	if(replace_by_title===undefined)
	{
          replace_by_title= 0;
    }
	fd.append("replace-by-title", replace_by_title);
	
	console.log("Lines:" + csv_content.length);
	var partPercentage = Math.round((csv_content.length / total_lineCount) * 100);
	$.ajax({
		type: "POST",
		url: ajax_object.ajaxurl,
		data: fd,
		//use contentType, processData for sure.
		contentType: false,
		processData: false,
		beforeSend: function() {
			/*$('#loading').prepend('<img src="' + ajax_object.load_img_url + '"/>');*/
		},
		success: function(msg) {
			loading(partPercentage);
			var msg_obj = JSON.parse(msg);
			$('#contents').append(msg_obj.message);
            
            if(splitInfo.current_part < splitInfo.total_parts)
            {
                processFileDataUpload();
            }
		},
		error: function() {
			$("#contents").html(
				"<pre>Sorry! Couldn't process your request.</pre>"
			); // 
		}
	});
}

$(document).ready(function() 
{
	if(isAPIAvailable()) 
	{
		$('#upload').bind('change', handleFileSelect);
	}
});

function isAPIAvailable() {
  // Check for the various File API support.
  if (window.File && window.FileReader && window.FileList && window.Blob) {
	// Great success! All the File APIs are supported.
	return true;
  } else {
	// source: File API availability - http://caniuse.com/#feat=fileapi
	// source: <output> availability - http://html5doctor.com/the-output-element/
	document.writeln('The HTML5 APIs used in this form are only available in the following browsers:<br />');
	// 6.0 File API & 13.0 <output>
	document.writeln(' - Google Chrome: 13.0 or later<br />');
	// 3.6 File API & 6.0 <output>
	document.writeln(' - Mozilla Firefox: 6.0 or later<br />');
	// 10.0 File API & 10.0 <output>
	document.writeln(' - Internet Explorer: Not supported (partial support expected in 10.0)<br />');
	// ? File API & 5.1 <output>
	document.writeln(' - Safari: Not supported<br />');
	// ? File API & 9.2 <output>
	document.writeln(' - Opera: Not supported');
	return false;
  }
}

function handleFileSelect(evt) 
{
	var files = evt.target.files; // FileList object
	var file = files[0];

	// read the file metadata
	var output = ''
	  output += '<span style="font-weight:bold;">' + escape(file.name) + '</span><br />\n';
	  output += ' - FileType: ' + (file.type || 'n/a') + '<br />\n';
	  output += ' - FileSize: ' + file.size + ' bytes<br />\n';
	  output += ' - LastModified: ' + (file.lastModifiedDate ? file.lastModifiedDate.toLocaleDateString() : 'n/a') + '<br />\n';

	// read the file contents
	//printTable(file);

	splitCSVFile(file);

	// post the results
	$('#list').append(output);
}

function getLines(csv, start_index, end_index)
{
	var options = {
			delimiter: config.delimiter,
			separator: config.separator,
			headers:   config.headers,
			start: start_index,
			end  : end_index,
			state: 
			{
			  rowNum: 1,
			  colNum: 1
			},
			match: false
		  };
	
	
	return $.csv.parsers.splitLines(csv, options);
}

function getLineCount(csv)
{
	var lines = getLines(csv, false, false);
	return (config.headers)?lines.length-1:lines.length;
}

function processFileDataUpload()
{
    var html = '';
    var lines = [];
    var temp = [];
    var lines_temp = [];
    var temp_part = splitInfo.current_part+1;

    if(splitInfo.total_parts > 1)
    {
        html += '<tr><td>==============================>[Part' + temp_part + ']<============================</td>\r\n';
        html += '</tr>\r\n';
    }
    lines = getLines(splitInfo.csv, splitInfo.start_index, splitInfo.end_index);
    lines.unshift(splitInfo.headerLine);
    for(var j=0; j < lines.length; j++ )
    {
        html += '<tr><td>[' + splitInfo.lCount + ']' + lines[j] + '</td>\r\n';
        html += '</tr>\r\n';
        splitInfo.lCount++;
        lines_temp.push(lines[j] + '\n');
    }
    var file_name = "tempCsvPart-" + (splitInfo.current_part+1) + ".csv";
    uploadFile(file_name, lines_temp, splitInfo.lineCount);
    temp.push('<li><strong>', escape(file_name), '</strong></li>');
    splitInfo.start_index += config.linesPerPart;
    splitInfo.end_index += config.linesPerPart;
    splitInfo.current_part++ ;
    document.getElementById('list-output').innerHTML = '<ul>' + temp.join('') + '</ul>';
    $('#contents').append(html);
}

function splitCSVFile(file)
{
	var reader = new FileReader();
	reader.readAsText(file);
	reader.onload = function(event)
	{
		var csv = event.target.result;
		var headerLine = (config.headers)?getLines(csv, 1, 1):false;
		var html = '';
		var start = (config.headers)?1:0;
		var lineCount = getLineCount(csv);
		var total_parts = 0;
		var lCount=0;

		if( lineCount > config.MaxlinesPerPart )
		{
			config.linesPerPart = parseInt(config.MaxlinesPerPart);
			splitInfo.total_parts = parseInt((lineCount/config.linesPerPart))  + parseInt((lineCount % config.linesPerPart)?1:0);
		}else{
			config.linesPerPart = parseInt(lineCount);
			splitInfo.total_parts = 1;
		}
        splitInfo.current_part = 0;
		splitInfo.start_index = (config.headers)?2:1;
		splitInfo.end_index = config.linesPerPart + (splitInfo.start_index - 1);
        splitInfo.lCount = 0;
        splitInfo.headerLine = (config.headers)?getLines(csv, 1, 1):false;
        splitInfo.csv = event.target.result;
        splitInfo.lineCount = lineCount;

		/*
        var temp = [];
		var lines_temp = [];
		
		for(var i=0; i < total_parts; i++ )
		{
			var temp_part = part+1;
			if(total_parts>1)
			{
				html += '<tr><td>==============================>[Part' + temp_part + ']<============================</td>\r\n';
				html += '</tr>\r\n';
			}
			lines = getLines(csv, start_index, end_index);
			lines.unshift(headerLine);
			for(var j=0; j < lines.length; j++ )
			{
				html += '<tr><td>['+lCount+']' + lines[j] + '</td>\r\n';
				html += '</tr>\r\n';
				lCount++;
				lines_temp.push(lines[j] + '\n');
			}
			//lines_temp.join("\n");
			var file_name_full = file.name;
			var file_name = "tempCsvPart-" + (i+1) + ".csv";
			uploadFile(file_name, lines_temp, lineCount);
			temp.push('<li><strong>', escape(file_name), '</strong></li>');
			start_index += config.linesPerPart;
			end_index += config.linesPerPart;
			part++ ;
		}
        */
        processFileDataUpload();
		
		return;
	}
}

function printTable(file) 
{
  var reader = new FileReader();
  reader.readAsText(file);
  reader.onload = function(event){
	var csv = event.target.result;
	var data = $.csv.toArrays(csv);
	var html = '';
	for(var row in data) {
	  html += '<tr>\r\n';
	  for(var item in data[row]) {
		html += '<td>' + data[row][item] + '</td>\r\n';
	  }
	  html += '</tr>\r\n';
	}
	$('#contents').html(html);
  };
  reader.onerror = function(){ alert('Unable to read ' + file.fileName); };
}
