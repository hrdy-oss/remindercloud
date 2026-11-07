(function () {
  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    var input = document.getElementById(button.getAttribute('aria-controls'));
    var field = button.closest('.password-field');

    if (!input || !field) {
      return;
    }

    field.classList.add('is-ready');
    button.addEventListener('click', function () {
      var visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Sembunyikan kata sandi' : 'Lihat kata sandi';
      button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    });
  });

  var logoutDialog = document.querySelector('#logout-dialog');

  if (logoutDialog) {
    var logoutForm = null;
    var cancelLogout = logoutDialog.querySelector('[data-logout-cancel]');
    var confirmLogout = logoutDialog.querySelector('[data-logout-confirm]');

    document.querySelectorAll('[data-logout-form]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (form.dataset.logoutConfirmed === 'true') {
          delete form.dataset.logoutConfirmed;
          return;
        }

        event.preventDefault();
        logoutForm = form;
        logoutDialog.showModal();
      });
    });

    cancelLogout.addEventListener('click', function () {
      logoutDialog.close();
      logoutForm = null;
    });

    confirmLogout.addEventListener('click', function () {
      if (!logoutForm) {
        return;
      }

      var formToSubmit = logoutForm;
      logoutForm = null;
      logoutDialog.close();
      formToSubmit.dataset.logoutConfirmed = 'true';
      formToSubmit.requestSubmit();
    });
  }

  var drivePickerRoot = document.querySelector('[data-drive-picker]');

  var previewDialog = document.querySelector('#storage-preview-dialog');
  if (previewDialog) {
    var previewContent = previewDialog.querySelector('#storage-preview-content');
    var previewTitle = previewDialog.querySelector('#storage-preview-title');
    var previewClose = previewDialog.querySelector('[data-preview-close]');
    var docxLibraryPromise;
    var xlsxLibraryPromise;

    function loadPreviewLibrary(src, globalName) {
      if (window[globalName]) {
        return Promise.resolve(window[globalName]);
      }
      return new Promise(function (resolve, reject) {
        var script = document.createElement('script');
        script.src = src;
        script.onload = function () {
          window[globalName] ? resolve(window[globalName]) : reject(new Error('Pustaka pratinjau tidak tersedia.'));
        };
        script.onerror = function () { reject(new Error('Pustaka pratinjau gagal dimuat. Periksa koneksi internet.')); };
        document.head.appendChild(script);
      });
    }

    function previewMessage(message) {
      var text = document.createElement('p');
      text.textContent = message;
      previewContent.replaceChildren(text);
    }

    document.querySelectorAll('[data-file-preview]').forEach(function (button) {
      button.addEventListener('click', async function () {
        var fileId = button.dataset.filePreview;
        var mime = (button.dataset.fileType || '').toLowerCase();
        var name = button.dataset.fileName || 'Pratinjau file';
        var url = 'index.php?p=storage&preview=' + encodeURIComponent(fileId);
        previewTitle.textContent = name;
        previewMessage('Memuat pratinjau…');
        previewDialog.showModal();

        try {
          if (mime.indexOf('image/') === 0) {
            var image = document.createElement('img');
            image.alt = name;
            image.src = url;
            previewContent.replaceChildren(image);
          } else if (mime.indexOf('video/') === 0) {
            var video = document.createElement('video');
            video.controls = true;
            video.preload = 'metadata';
            video.src = url;
            previewContent.replaceChildren(video);
          } else if (mime.indexOf('audio/') === 0) {
            var audio = document.createElement('audio');
            audio.controls = true;
            audio.src = url;
            previewContent.replaceChildren(audio);
          } else if (mime === 'application/pdf' || mime === 'text/plain' || mime === 'application/json') {
            var frame = document.createElement('iframe');
            frame.title = name;
            frame.src = url;
            previewContent.replaceChildren(frame);
          } else if (/\.docx$/i.test(name) || mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            docxLibraryPromise ||= loadPreviewLibrary('https://cdn.jsdelivr.net/npm/docx-preview@0.3.6/dist/docx-preview.min.js', 'docx');
            await docxLibraryPromise;
            var response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('File tidak dapat dimuat.');
            var docxData = await response.arrayBuffer();
            previewContent.replaceChildren();
            await window.docx.renderAsync(docxData, previewContent, null, { breakPages: true });
          } else if (/\.(xlsx|xls)$/i.test(name) || mime.indexOf('spreadsheet') !== -1 || mime === 'application/vnd.ms-excel') {
            xlsxLibraryPromise ||= loadPreviewLibrary('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js', 'XLSX');
            await xlsxLibraryPromise;
            var sheetResponse = await fetch(url, { credentials: 'same-origin' });
            if (!sheetResponse.ok) throw new Error('File tidak dapat dimuat.');
            var workbook = window.XLSX.read(await sheetResponse.arrayBuffer(), { type: 'array' });
            var sheetName = workbook.SheetNames[0];
            var rows = window.XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { header: 1, raw: true }).slice(0, 500);
            var table = document.createElement('table');
            table.className = 'preview-sheet';
            rows.forEach(function (row) {
              var tr = document.createElement('tr');
              row.slice(0, 30).forEach(function (cell) {
                var td = document.createElement('td');
                td.textContent = cell == null ? '' : String(cell);
                tr.appendChild(td);
              });
              table.appendChild(tr);
            });
            previewContent.replaceChildren(table);
          } else {
            previewMessage('Pratinjau untuk format ini belum tersedia. Kamu masih bisa mengunduh file.');
          }
        } catch (error) {
          previewMessage(error.message || 'Pratinjau gagal dimuat.');
        }
      });
    });

    previewClose.addEventListener('click', function () {
      previewDialog.close();
      previewContent.replaceChildren();
    });
    previewDialog.addEventListener('close', function () { previewContent.replaceChildren(); });
  }

  if (drivePickerRoot) {
    var driveOpenButton = drivePickerRoot.querySelector('[data-drive-open]');
    var driveStatus = drivePickerRoot.querySelector('[data-drive-status]');
    var driveToken = '';
    var tokenClient = null;

    function setDriveStatus(message) {
      if (driveStatus) {
        driveStatus.textContent = message;
      }
    }

    function waitForPickerLibraries() {
      return new Promise(function (resolve, reject) {
        var attempts = 0;

        (function wait() {
          if (
            window.google && google.accounts && google.accounts.oauth2
            && window.gapi && typeof gapi.load === 'function'
          ) {
            gapi.load('picker', { callback: resolve });
            return;
          }

          attempts += 1;
          if (attempts >= 100) {
            reject(new Error('Google Picker belum termuat. Periksa koneksi internet dan API yang diaktifkan.'));
            return;
          }

          window.setTimeout(wait, 100);
        })();
      });
    }

    function getDriveToken() {
      return new Promise(function (resolve, reject) {
        tokenClient = google.accounts.oauth2.initTokenClient({
          client_id: drivePickerRoot.dataset.clientId,
          scope: 'https://www.googleapis.com/auth/drive.file',
          callback: function (response) {
            if (response.error) {
              reject(new Error(response.error_description || response.error));
              return;
            }

            driveToken = response.access_token;
            resolve(driveToken);
          },
          error_callback: function (error) {
            reject(new Error(error.message || 'Izin Google Drive tidak diberikan.'));
          }
        });

        tokenClient.requestAccessToken({ prompt: driveToken ? '' : 'consent' });
      });
    }

    var pickerLibraryPromise = waitForPickerLibraries();
    document.querySelectorAll('[data-drive-delete]').forEach(function (button) {
      button.disabled = true;
    });
    pickerLibraryPromise.then(function () {
      if (driveOpenButton) {
        driveOpenButton.disabled = false;
      }
      document.querySelectorAll('[data-drive-delete]').forEach(function (button) {
        button.disabled = false;
      });
      setDriveStatus('Google Picker siap. File akan tersimpan di Drive akunmu.');
    }).catch(function (error) {
      setDriveStatus(error.message);
    });

    async function saveDriveFile(documentInfo) {
      var metadataUrl = 'https://www.googleapis.com/drive/v3/files/'
        + encodeURIComponent(documentInfo.id)
        + '?fields=id,name,mimeType,size&key='
        + encodeURIComponent(drivePickerRoot.dataset.apiKey);
      var metadataResponse = await fetch(metadataUrl, {
        headers: { Authorization: 'Bearer ' + driveToken }
      });

      if (!metadataResponse.ok) {
        throw new Error('Tidak bisa membaca detail file Drive yang dipilih.');
      }

      var metadata = await metadataResponse.json();
      var formData = new FormData();
      formData.append('_t', drivePickerRoot.dataset.csrf);
      formData.append('drive_id', metadata.id);
      formData.append('name', metadata.name || documentInfo.name || 'File Drive');
      formData.append('mime_type', metadata.mimeType || documentInfo.mimeType || 'application/octet-stream');
      formData.append('file_size', metadata.size || documentInfo.sizeBytes || '0');

      var saveUrl = new URL('index.php?a=drive_file_save', window.location.href);
      var saveResponse = await fetch(saveUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      if (!saveResponse.ok) {
        throw new Error('Gagal menyimpan data file ke daftar Storage.');
      }

      var result = await saveResponse.json();
      if (!result.ok) {
        throw new Error(result.message || 'Gagal menyimpan file.');
      }
    }

    async function openDrivePicker() {
      if (!driveOpenButton) {
        return;
      }

      driveOpenButton.disabled = true;
      setDriveStatus('Menyambungkan ke Google Drive…');

      try {
        var tokenPromise = getDriveToken();
        await tokenPromise;

        var picker = new google.picker.PickerBuilder()
          .addView(new google.picker.DocsUploadView())
          .addView(new google.picker.DocsView(google.picker.ViewId.DOCS))
          .setOAuthToken(driveToken)
          .setDeveloperKey(drivePickerRoot.dataset.apiKey)
          .setAppId(drivePickerRoot.dataset.appId)
          .setOrigin(window.location.protocol + '//' + window.location.host)
          .setCallback(async function (data) {
            if (data.action === google.picker.Action.CANCEL) {
              driveOpenButton.disabled = false;
              setDriveStatus('Pemilihan file dibatalkan.');
              return;
            }

            if (data.action !== google.picker.Action.PICKED || !data.docs || !data.docs.length) {
              return;
            }

            setDriveStatus('Menyimpan file ke daftar…');
            try {
              for (var i = 0; i < data.docs.length; i += 1) {
                await saveDriveFile(data.docs[i]);
              }
              window.location.reload();
            } catch (error) {
              setDriveStatus(error.message);
              driveOpenButton.disabled = false;
            }
          })
          .build();

        picker.setVisible(true);
        setDriveStatus('Pilih file di Drive atau unggah file baru.');
      } catch (error) {
        setDriveStatus(error.message);
        driveOpenButton.disabled = false;
      }
    }

    if (driveOpenButton) {
      driveOpenButton.addEventListener('click', openDrivePicker);
    }

    document.querySelectorAll('[data-drive-delete]').forEach(function (button) {
      button.addEventListener('click', async function () {
        var driveId = button.dataset.driveDelete;
        if (!window.confirm('Pindahkan file ini ke sampah Google Drive?')) {
          return;
        }

        button.disabled = true;
        setDriveStatus('Memindahkan file ke sampah Drive…');

        try {
          var tokenPromise = getDriveToken();
          await tokenPromise;

          var driveDeleteUrl = 'https://www.googleapis.com/drive/v3/files/' + encodeURIComponent(driveId);
          var driveResponse = await fetch(driveDeleteUrl, {
            method: 'PATCH',
            headers: {
              Authorization: 'Bearer ' + driveToken,
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({ trashed: true })
          });

          if (!driveResponse.ok) {
            throw new Error('Google Drive menolak pemindahan file ke sampah.');
          }

          var formData = new FormData();
          formData.append('_t', drivePickerRoot.dataset.csrf);
          formData.append('drive_id', driveId);
          var forgetUrl = new URL('index.php?a=drive_file_forget', window.location.href);
          var forgetResponse = await fetch(forgetUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });

          if (!forgetResponse.ok) {
            throw new Error('File sudah dipindahkan ke sampah, tetapi daftar Storage gagal diperbarui.');
          }

          window.location.reload();
        } catch (error) {
          setDriveStatus(error.message);
          button.disabled = false;
        }
      });
    });
  }

  if (matchMedia('(prefers-reduced-motion:reduce)').matches) {
    return;
  }

  document.querySelectorAll('.stats b').forEach(function (element) {
    var target = +element.textContent;

    if (!target) {
      return;
    }

    var startedAt = performance.now();
    element.textContent = '0';

    (function animate(timestamp) {
      var progress = Math.min((timestamp - startedAt) / 800, 1);
      var easedProgress = 1 - Math.pow(1 - progress, 4);

      element.textContent = Math.round(target * easedProgress);

      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    })(startedAt);
  });

  document.querySelectorAll('.bar i').forEach(function (element) {
    var targetWidth = element.style.width;
    element.style.width = '0';

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        element.style.width = targetWidth;
      });
    });
  });
})();
