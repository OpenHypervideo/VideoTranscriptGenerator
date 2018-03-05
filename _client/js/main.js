var isMouseOver = false,
	selectedXMLFile = null,
	xPath = null;

$(document).ready( function() {
	
	$.ajax('_server/ajaxServer.php?a=getXMLFiles')
		.done(function(data) {
			if (data.files) {
				for (var i=0; i<data.files.length; i++) {
					var listItem = $('<li>'+ data.files[i] +'</li>');

					$('#fileList').append(listItem);
				}
			}
		})
		.fail(function() {
			//
		});

	$('#fileList').on('click', 'li', function(evt) {
		$('#fileList li').removeClass('active');
		$(this).addClass('active');
		selectedXMLFile = $(this).text();
		updateForceAlignButton();

		getXMLTableOfContents($(this).text());
	});

	$('#xmlContents').on('click', 'li', function(evt) {
		var isActive = $(this).hasClass('active');
		
		$('#xmlContents li').removeClass('active');
		
		if (!isActive) {
			$(this).addClass('active');
			xPath = getXMLxPath($(this));
		} else {
			$(this).removeClass('active');
			xPath = null;
		}
				
		updateForceAlignButton();
	});

	$('#startProcessing').click(function() {

		$('#video, #transcript, #status').empty();
		$('.progressIndicator').width(0).removeClass('error success');
		$('.glyphicon').removeClass('active');

		$('.nav-tabs a[href="#outputContainer"]').click();

		forceAlignXML();
	});

	$('#transcript').hover(function() {
		isMouseOver = true;
	}, function() {
		isMouseOver = false;
	});

	updateForceAlignButton();
	
});

function getXMLTableOfContents(xmlFilePath) {

	$('#xmlContents').empty();

	$.ajax({
		method: 'GET',
		dataType: 'xml',
		url: '_server/'+ xmlFilePath,
	})
		.done(function(xml) {
			var xmlData = $(xml);
			var titel = xmlData.find('kopfdaten').eq(0).text();

			$('#xmlContents').append('<h5>'+ titel +'</h5>');

			var inhalt = xmlData.find('inhaltsverzeichnis');

			var inhaltList = $('<ul></ul>');
			
			inhaltList.append('<li data-type="top">Sitzungseröffnung</li>');

			var openingPointsList = $('<ul></ul>'),
				openingEnd = false;

			inhaltList.append(openingPointsList);

			inhalt.children('ivz-eintrag, ivz-block').each(function() {
				
				if (this.nodeName == 'ivz-eintrag') {
					
					var listItem = $('<li data-type="'+ getItemCategory($(this)) +'">'+ getCleanTOP($(this).children('ivz-eintrag-inhalt').html()) +'</li>');
					
					if (listItem.attr('data-type') == 'rede') {
						listItem.attr('data-rede-id', $(this).children('xref').attr('rid'));
					}

					if (!openingEnd) {
						openingPointsList.append(listItem);
					} else {
						inhaltList.append(listItem);
					}

				} else if (this.nodeName == 'ivz-block') {
					
					openingEnd = true;

					inhaltList.append('<li data-type="'+ getItemCategory($(this)) +'">'+ getCleanTOP($(this).children('ivz-block-titel').html()) +'</li>');
					var levelTwo = $('<ul></ul>');

					$(this).children('ivz-eintrag').each(function() {
						
						var listItem = $('<li data-type="'+ getItemCategory($(this)) +'">'+ getCleanTOP($(this).children('ivz-eintrag-inhalt').html()) +'</li>');
						
						if (listItem.attr('data-type') == 'rede') {
							listItem.attr('data-rede-id', $(this).children('xref').attr('rid'));
						}

						levelTwo.append(listItem);

					});

					inhaltList.append(levelTwo);
				}
				
			});

			$('#xmlContents').append(inhaltList);
		})
		.fail(function() {
			//
		});

}

function getXMLxPath(listElement) {

	var path = '',
		searchText = listElement.text();

	if (listElement.attr('data-type') == 'rede') {
		var redeID = listElement.attr('data-rede-id');
		path = '//rede[@id="'+ redeID +'"]';
	} else if (searchText.indexOf('Tagesordnungspunkt') != -1) {
		var topID = searchText.match(/(Tagesordnungspunkt) [0-9]+/)[0];
		path = '//tagesordnungspunkt[@top-id="'+ topID +'"]';
	} else if (searchText.indexOf('Zusatztagesordnungspunkt') != -1) {
		var topID = searchText.match(/(Zusatztagesordnungspunkt) [0-9]+/)[0].replace('Zusatztagesordnungspunkt', 'Zusatzpunkt');
		path = '//tagesordnungspunkt[@top-id="'+ topID +'"]';
	} else if (searchText == 'Sitzungseröffnung') {
		path = '//sitzungsbeginn';
	}

	return path;
}

function getItemCategory(itemElement) {
	
	var nodeName = itemElement[0].nodeName,
		searchText = '',
		category = '';

	if (nodeName == 'ivz-eintrag') {
		searchText = itemElement.children('ivz-eintrag-inhalt').html();
	} else if (nodeName == 'ivz-block') {
		searchText = itemElement.children('ivz-block-titel').html();
	}

	if (searchText.indexOf('<redner') != -1) {
		category = 'rede';
	} else if (searchText.indexOf('Tagesordnungspunkt') != -1 || searchText.indexOf('Zusatztagesordnungspunkt') != -1) {
		category = 'top';
	} else {
		category = 'misc';
	}

	return category;
}

function getCleanTOP(TOPString) {

	var cleanString = TOPString;
	
	if (TOPString.indexOf('<redner') != -1) {
		cleanString = 'Rede: '+ TOPString.replace(/(<redner)(.|\n)*?(redner>)/, '');
	}

	return cleanString;
}

function updateForceAlignButton() {
	if (selectedXMLFile) {
		$('#startProcessing').prop('disabled', false);
	} else {
		$('#startProcessing').prop('disabled', true);
	}
	console.log(selectedXMLFile, xPath);
}

function forceAlignXML() {

	if (!window.XMLHttpRequest){
		console.log('Browser does not support native XMLHttpRequest.');
		return;
	}
	try{
		var xhr = new XMLHttpRequest();  
		xhr.previous_text = '';
									 
		xhr.onerror = function() { console.log('[XHR] Error.'); };
		xhr.onreadystatechange = function() {
			try{
				if (xhr.readyState == 4){
					//console.log('[XHR] PHP Done');
				} 
				else if (xhr.readyState > 2){
					//console.log(xhr.responseText);

					var new_response = xhr.responseText.substring(xhr.previous_text.length);                    
					
					var new_response_parts = new_response.split('{');

					for (var i=0; i<new_response_parts.length; i++) {

						//console.log(new_response_parts[i]);

						if (new_response_parts[i].length == 0) {
							continue;
						}

						var result = JSON.parse('{'+ new_response_parts[i]);

						
						if (result.video && result.html) {
							generatePreview(result.video, result.html);
						}

						if ((result.task != 'download') || (result.task == 'download' && result.progress == 100 && result.status == 'success')) {
							$('#status')[0].innerHTML += '<div class="'+ result.status +'">'+ result.message +'</div>';
							$('#status')[0].scrollTop = $('#status')[0].scrollHeight;
						}
						
						if (result.task == 'download') {
							$('#loadingProgress').width(result.progress + '%');
							if (result.progress == 100) {
								$('#loadingProgress').addClass('success');
								$('.glyphicon#audioOK').addClass('active');
							}
						} else if (result.task == 'forcealign') {
							$('#forceAlignProgress').width(result.progress + '%');
							if (result.progress == 100) {
								$('#forceAlignProgress').addClass('success');
								$('.glyphicon#forceAlignOK').addClass('active');
							}
							if (result.status == 'error') {
								$('#forceAlignProgress').addClass('error');
							}
						}
					}

					xhr.previous_text = xhr.responseText;
				}  
			}
			catch (e){
				console.log('[XHR STATECHANGE] Exception: ' + e);
			}                     
		};
		xhr.open('GET', '_server/ajaxServer.php?a=forceAlign&xmlPath='+ selectedXMLFile +'&xPath='+ xPath, true);
		xhr.send();      
	}
	catch (e){
		console.log('[XHR REQUEST] Exception: ' + e);
	}

}

function generatePreview(videoURL, htmlString) {

	var videoElem = $('<video src="'+ videoURL +'" type="video/mp4" controls/>');
	var htmlContent = $($.parseXML(htmlString)).find('div').eq(0).html();

	videoElem.on('timeupdate', checkTimings);

	$('#video').append(videoElem);
	$('#transcript').append(htmlContent);

	$('#transcript').find('.timebased').click(function() {
		$('#video video')[0].currentTime = parseFloat($(this).attr('data-start'));
	});

}

function checkTimings() {

	var currentTime = $('#video video')[0].currentTime;

	var timebasedElements = $('#transcript').find('.timebased');

	if ( timebasedElements.length != 0 ) {
		timebasedElements.each(function() {
			var startTime = parseFloat($(this).attr('data-start')),
				endTime = parseFloat($(this).attr('data-end'));
			if ( startTime-0.5 <= currentTime && endTime-0.5 >= currentTime ) {
				if ( !$(this).hasClass('active') ) {
					$(this).addClass('active');
					scrollTimebasedElements();
				}
			} else if ( $(this).hasClass('active') ) {
				$(this).removeClass('active');
			}
		});
	}

}

function scrollTimebasedElements() {
	
	if (isMouseOver) {
		return;
	}
	var customhtmlContainer = $('#transcript'),
		firstActiveElement = customhtmlContainer.find('.timebased.active').eq(0);


	if ( firstActiveElement.length == 0 ) {
		return;
	}

	var activeElementPosition = firstActiveElement.position();

	if ( activeElementPosition.top <
		customhtmlContainer.height()/2 + customhtmlContainer.scrollTop()
		|| activeElementPosition.top > customhtmlContainer.height()/2 + customhtmlContainer.scrollTop() ) {

		var newPos = activeElementPosition.top + customhtmlContainer.scrollTop() - customhtmlContainer.height()/2;
		customhtmlContainer.stop().animate({scrollTop : newPos},400);
	}

}

