define [
  'wm/app'
  'wm/config'
], (app, config) ->
  app.registerService 'downloadImageService', [
    '$timeout'
    '$translate'
    '$filter'
    '$interval'
    'restService'
    'messageService'
    '$rootScope'
    'notificationService'
    'localStorageService'
    ($timeout, $translate, $filter, $interval, restService, messageService, $rootScope, notificationService, localStorageService) ->
      vm = {}

      secs =
        overtime: 30 * 60 * 1000
        hide: 10 * 1000
        checkStatus: 30 * 1000
        removePreparedJob: 60 * 1000

      fileType = 'zip'

      _elemFadeOut = ($elem, isRemove, callback) ->
        $exportWrapper = $('.export-wrapper')
        isRemove = isRemove or false
        $elem.fadeOut ->
          id = $elem.attr('id')
          exportJob = _getExportJob(id)
          exportJob.width = $elem.find('.progress-bar').width() if $elem.find('.progress-bar').length and exportJob

          $elem.remove() if isRemove
          callback() if callback

          if $exportWrapper.find('.message').length is 0 and vm.exportJobs
            preparedLength = vm.exportJobs.filter( (exportJob) ->
              return exportJob.isPrepared
            ).length
            totalLength = vm.exportJobs.length
            if totalLength isnt 0
              vm.showLoadingMessage(preparedLength, totalLength)
            else
              _emptyMessage()
        return

      _elemFadeIn = ($elem, callback) ->
        $elem.fadeIn( ->
          callback() if callback
        )
        return

      # according the job id to get the export job
      _getExportJob = (key, value, hasPrepared) ->
        exportJob = null
        if vm.exportJobs and angular.isArray vm.exportJobs
          if value?
            angular.forEach vm.exportJobs, (item) ->
              if hasPrepared?
                exportJob = item if item[key] and value is item[key]
              else
                exportJob = item if item[key] and value is item[key] and item.isPrepared is hasPrepared
          else
            angular.forEach vm.exportJobs, (item) ->
              exportJob = item if item.id and key is item.id
        return exportJob

      # according the job id to get the export job index
      _getExportJobIndex = (key, value, hasPrepared) ->
        idx = -1
        if vm.exportJobs and angular.isArray vm.exportJobs
          if value?
            angular.forEach vm.exportJobs, (item, index) ->
            if hasPrepared?
                idx = index if item[key] and value is item[key]
              else
                idx = index if item[key] and value is item[key] and item.isPrepared is hasPrepared
          else
            angular.forEach vm.exportJobs, (item, index) ->
              idx = index if item.id and key is item.id
        return idx

      # start the progress
      _startExportBoxProgress = (id, step, cancelback) ->
        $elem = $("##{id}")

        $progress = $elem.find('.progress')
        $progressBar = $elem.find('.progress-bar')

        totalWidth = $progress.width()

        id = $elem.attr('id')
        exportJob = _getExportJob(id)

        countTime = $interval( ->
          totalWidth = totalWidth or $progress.width()

          step = step or (totalWidth / 50)
          presentWidth = $progressBar.width()
          progress = presentWidth / totalWidth
          if progress >= 0.9
            _clearTimer exportJob, 'progress'
            cancelback() if cancelback
          else
            $progressBar.width(presentWidth + step)
          return
        , 500)

        exportJob.progressTimer = countTime if exportJob
        return

      # set the progress bar 90% and stop increasing
      _stopExportBoxProgress = (id) ->
        $("##{id}").find('.progress-bar').css('width', '90%')
        return

      # set the progress bar 100%
      _completeExportBoxProgress = (id) ->
        $("##{id}").find('.progress-bar').css('width', '100%')
        exportJob = _getExportJob(id)
        _clearTimer exportJob, 'progress'
        return

      # clear timer about hide message box after 10s and control propress
      _clearTimer = (exportJob, type) ->
        switch type
          when 'progress'
            if exportJob and exportJob.progressTimer
              $interval.cancel(exportJob.progressTimer)
              delete exportJob.progressTimer
          when 'hide'
            if exportJob and exportJob.hideTimer
              $timeout.cancel(exportJob.hideTimer)
              delete exportJob.hideTimer
          when 'overtime'
            if exportJob and exportJob.overTimer
              $timeout.cancel(exportJob.overTimer)
              delete exportJob.overTimer
        return

      # according exprot jobs id and key get download link
      _getJobsStatus = (params, callback) ->
        restService.noLoading().post "/api/game/job/status", params, (data) ->
          if data
            callback(data) if callback

      _setDownLink = (btn, exportJob) ->
        btn.attr 'href', exportJob.link
        btn.attr 'download', ''

        btn.click (event) ->
          vm.removeJob exportJob.id
          _emptyMessage() if vm.exportJobs.length is 0
          event.stopPropagation()
          return

      _restoreDownMessage = ($elem, exportJob, content) ->
        if not content
          if not plain
            params = {'name': $filter('translate')(exportJob.name).toLowerCase()}
            content = $filter('translate')('prepared_download_image', params)

        $elem.find('.content-download').text content

        $downloadBtn = $elem.find('.btn-download')

        btnText = $filter('translate')('download')
        $downloadBtn.text btnText
        $elem.attr 'id', exportJob.id

        if not exportJob.link
          params =
            data: [{jobId: exportJob.id, key: exportJob.key, type: fileType}]
          _getJobsStatus params, (data) ->
            if data and angular.isArray(data) and data.length > 0
              exportJob.link = data[0].url
              _setDownLink($downloadBtn, exportJob)
        else
          _setDownLink($downloadBtn, exportJob)

      # remove all message boxes and delete all export jobs
      _emptyMessage = ->
        $exportWrapper = $('.export-wrapper')
        vm.exportJobs = []
        $exportWrapper.empty()
        _removeCheckJobsStatusTimer()
        _removeOvertimePreparedJobTimer()

      _addExportMessage = (id, name, plain) ->
        if not plain
          params = {'name': $filter('translate')(name)}
          name = $filter('translate')('download_title', params)
        _addMessage id, name, 'preparing'
        return

      _addDownloadMessage = (id, name, plain) ->
        if not plain
          params = {'name': $filter('translate')(name).toLowerCase()}
          name = $filter('translate')('prepared_download_image', params)
        _addMessage id, name, 'download'
        return

      # according the type to add preparing data and download message box
      _addMessage = (id, content, type) ->
        $exportWrapper = $('.export-wrapper')
        $messageBox = $ $exportWrapper.find("##{id}")[0] if $exportWrapper.find("##{id}").length > 0
        exportJob = _getExportJob(id)

        $loadingBox = $exportWrapper.find('.message-loading')
        if $loadingBox.length > 0
          $loadingBox.remove()

        switch type
          when 'preparing'
            if not $messageBox
              messageBox = '<ul class="message message-preparing">
                              <div class="progress">
                                <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                                </div>
                              </div>
                              <div class="message-content">
                                <span class="text content-title"></span>
                                <span class="text content-preparing"></span>
                              </div>
                              <i class="close-btn"></i>
                            </ul>'
              $messageBox = $ messageBox
              $exportWrapper.append $messageBox
            else
              _clearTimer exportJob, 'hide'

            $messageBox.find('span.content-title').html content

            preparingText = $filter('translate')('preparing_download_image')
            $messageBox.find('span.content-preparing').text preparingText
            $messageBox.attr 'id', id

            $messageBox.find('.progress-bar').width(exportJob.width) if exportJob.width

            $messageBox.ready ->
              _startExportBoxProgress id

          when 'download'
            if not $messageBox
              messageBox = '<ul class="message message-download">
                              <div class="message-content">
                                <span class="text content-download"></span>
                              </div>
                              <a class="btn btn-success btn-download" href="#"></a>
                              <i class="close-btn"></i>
                            </ul>'
              $messageBox = $ messageBox
              $exportWrapper.append $messageBox

              _restoreDownMessage $messageBox, exportJob, content
            else
              _elemFadeOut $messageBox, false, ->
                $messageBox.empty()
                $messageBox.removeClass('message-preparing').addClass('message-download')
                $contentBox = $ '<div class="message-content">
                                  <span class="text content-download"></span>
                                </div>'
                $downloadBox = $ '<a class="btn btn-success btn-download" href="#"></a>'
                $closeBox = $ '<i class="close-btn"></i>'
                $messageBox.append $contentBox
                $messageBox.append $downloadBox
                $messageBox.append $closeBox

                _clearTimer exportJob, 'hide'
                _elemFadeIn $messageBox, ->
                  _restoreDownMessage $messageBox, exportJob, content

        $messageBox.slideDown 'fast'
        $messageBox.addClass 'message-' + type

        $messageBox.click ->
          _elemFadeOut $messageBox, true, ->
            _clearTimer exportJob, 'hide'
          return

        $messageBox.children('.close-btn').click ->
          _elemFadeOut $messageBox, true, ->
            _clearTimer exportJob, 'hide'
          return

        # hide after 10s
        timer = $timeout ->
          _elemFadeOut($messageBox, true)
        , secs.hide
        exportJob.hideTimer = timer if exportJob

        #remove the first message if it already has 4
        ###
        if $('.export-wrapper').children().length > 4
          $('.message:first').fadeOut ->
            $('.message:first').remove()
        ###

      _bindPreparedEvent = ->
        messageService.bind 'export_finish', (data) ->
          if data
            key = data.key
            exportJob = _getExportJob('key', key, false)
            vm.download(exportJob.id) if exportJob and not exportJob.isPrepared

      _exportHandler = (data, callback) ->
        type = data.result
        switch type
          when 'success'
            if data.data
              item =
                id: data.data.jobId
                key: data.data.key
              callback(item) if callback
          when 'error'
            notificationService.error 'export_failed', false

      _export = (url, params, okCallback, failCallback) ->
        if 'function' is typeof (params)
          failCallback = okCallback
          okCallback = params
          params = {}

        restService.get url, params, (data) ->
          _exportHandler(data, okCallback)
        , (res) ->
          failCallback(res)

      _exportJobFailed = (exportJob) ->
        type = exportJob.name.replace(/_/g, '-')
        $rootScope.$broadcast 'exportDataPrepared', type, exportJob.params

        _clearTimer exportJob, 'overtime'
        vm.removeJob(exportJob.id)
        params =
          'name': ''
        if not exportJob.plain
          params.name = $filter('translate')(exportJob.name).toLowerCase()
        notificationService.error 'export_failed', false, params

      _createCheckJobsStatusTimer = ->
        if vm.exportJobs and (angular.isArray(vm.exportJobs) and vm.exportJobs.length isnt 0)
          if not vm.checkJobsStatusTimer
            vm.checkJobsStatusTimer = $interval( ->
              params =
                data: []

              preparingJobs = vm.exportJobs.filter (exportJob) ->
                return not exportJob.isPrepared

              angular.forEach preparingJobs, (exportJob) ->
                option =
                  jobId: exportJob.id
                  key: exportJob.key
                  type: fileType
                params.data.push option

              if params.data.length > 0
                _getJobsStatus params, (data) ->
                  if data and angular.isArray(data) and data.length > 0
                    angular.forEach data, (status) ->
                      exportJob = _getExportJob(status.jobId)
                      switch status.status
                        when 'complete'
                          if exportJob
                            exportJob.isPrepared = true
                            exportJob.link = status.url
                            vm.download(exportJob.id)
                        when 'failed'
                          _exportJobFailed exportJob
                        when 'error'
                          _exportJobFailed exportJob
              else
                _removeCheckJobsStatusTimer()
            , secs.checkStatus)

      _createOvertimePreparedJobTimer = ->
        preparedJobs = vm.exportJobs.filter( (exportJob) ->
          return exportJob.isPrepared
        )

        if preparedJobs.length isnt 0 and not vm.overtimePreparedJob
          vm.overtimePreparedJob = $interval ->
            preparedJobs = vm.exportJobs.filter( (exportJob) ->
              return exportJob.isPrepared
            )

            preparedLength = preparedJobs.length

            angular.forEach preparedJobs, (preparedJob) ->
              preparedJob.overtime++

              if preparedJob.overtime is 60
                vm.removeJob(preparedJob.id)
          , secs.removePreparedJob


      # cancel timer about remove prepared job every 1 hour
      _removeOvertimePreparedJobTimer = ->
        if vm.overtimePreparedJob
          $interval.cancel vm.overtimePreparedJob
          delete vm.overtimePreparedJob

      _removeCheckJobsStatusTimer = ->
        if vm.checkJobsStatusTimer
          $interval.cancel vm.checkJobsStatusTimer
          delete vm.checkJobsStatusTimer

      # according exportJobs list to show preparing and download messsage box
      vm.showMessage = ->
        angular.forEach vm.exportJobs, (exportJob) ->
          if exportJob.isPrepared
            _addDownloadMessage exportJob.id, exportJob.name, exportJob.plain
          else
            _addExportMessage exportJob.id, exportJob.name, exportJob.plain

      # show export loading message box
      vm.showLoadingMessage = (preparedLength, totalLength) ->
        if preparedLength is totalLength
          _removeCheckJobsStatusTimer()
        content = "#{preparedLength}/#{totalLength}"
        $exportWrapper = $('.export-wrapper')
        $exportWrapper.empty()
        messageBox = '<ul class="message message-loading">
                        <div class="store-synchronize-icon synchronize-loading"></div>
                        <div class="message-content message-count"></div>
                      </ul>'
        $messageBox = $ messageBox
        $exportWrapper.append $messageBox

        angular.forEach vm.exportJobs, (exportJob) ->
          _clearTimer(exportJob, 'progress')

        $messageBox.find('.message-count').text content
        $messageBox.css 'display', 'inline-block'

        $loadingBox = $messageBox.find('.store-synchronize-icon')
        if preparedLength is totalLength
          $loadingBox.css 'border-color', '#37b9a0'

        $messageBox.click ->
          $exportWrapper.empty()
          vm.showMessage()
        return

      # destory export wrapper
      vm.destory = ->
        # remove timer about export job
        angular.forEach vm.exportJobs, (exportJob) ->
          _clearTimer(exportJob, 'progress')
          _clearTimer(exportJob, 'hide')
          _clearTimer(exportJob, 'overtime')
        _emptyMessage()
        delete vm.exportJobs

      # according id remove export job
      vm.removeJob = (id) ->
        $elem = $("##{id}")
        idx = _getExportJobIndex id
        _clearTimer(vm.exportJobs[idx], 'progress')
        _clearTimer(vm.exportJobs[idx], 'hide')
        _clearTimer(vm.exportJobs[idx], 'overtime')
        vm.exportJobs.splice idx, 1 if idx isnt -1

        preparedLength = vm.exportJobs.filter( (exportJob) ->
          return exportJob.isPrepared
        ).length
        totalLength = vm.exportJobs.length

        if $elem.length > 0
          _elemFadeOut($elem, true)
        else if totalLength isnt 0
          vm.showLoadingMessage(preparedLength, totalLength)
        else
          _emptyMessage()

        _removeOvertimePreparedJobTimer() if preparedLength is 0

        localStorageService.updateItem config.keys.exportJobs, vm.exportJobs if vm.exportJobs

      vm.refreshReloadJobs = ->
        jobs = angular.copy localStorageService.getItem config.keys.exportJobs
        if jobs and angular.isArray(jobs)

          # remove the job which saved time above 1 hour or 10 mins
          currentTimeStamp = moment().valueOf()
          jobs = angular.copy jobs.filter (item) ->

            # if the job's data has repared, saved time should't above 1 hour
            if item.isPrepared
              limitTime = 60 * secs.removePreparedJob
            else
              limitTime = secs.overtime
            item.savedTime and currentTimeStamp - item.savedTime < limitTime

          vm.exportJobs = angular.copy jobs

          if jobs.length > 0
            preparedLength = vm.exportJobs.filter( (exportJob) ->
              return exportJob.isPrepared
            ).length
            totalLength = vm.exportJobs.length

            _createCheckJobsStatusTimer() if (totalLength - preparedLength) isnt 0

            _createOvertimePreparedJobTimer() if preparedLength isnt 0 and not vm.overtimePreparedJob

            vm.showLoadingMessage(preparedLength, totalLength)

      # User create a export job, to add preparing data message box
      vm.export = (name, url, params, plain) ->
        if not vm.exportJobs or (angular.isArray(vm.exportJobs) and vm.exportJobs.length is 0)
          _bindPreparedEvent()

        _export url, params, (data) ->
          title = name.replace(/\-/g, '_')

          exportJob = _getExportJob data.id
          if not exportJob
            exportJob =
              id: data.id
              key: data.key
              name: title
              plain: plain
              isPrepared: false
              params: params
              overtime: 0

            if not vm.exportJobs or not angular.isArray vm.exportJobs
              vm.exportJobs = []
            vm.exportJobs.push exportJob

          overtime = $timeout ->
            _exportJobFailed exportJob
          , secs.overtime

          exportJob.overTimer = overtime

          _addExportMessage data.id, title, plain

          _createCheckJobsStatusTimer()
        , (res) ->
          # pop up error message after get export resources data error
          message = $.trim(res.message)
          if message and 'string' is typeof message
            #alert res.msg
            notificationService.error message, true
          else
            #alert 'Error'
            notificationService.error 'request_error'
          $rootScope.$broadcast 'exportDataPrepared', name, params

      # The export data is ready, to add download message box
      vm.download = (id, link) ->
        exportJob = _getExportJob id
        exportJob.isPrepared = true
        exportJob.link = link if link
        _completeExportBoxProgress(id)

        _clearTimer exportJob, 'overtime'

        preparedLength = vm.exportJobs.filter( (exportJob) ->
          return exportJob.isPrepared
        ).length
        totalLength = vm.exportJobs.length
        _removeCheckJobsStatusTimer() if preparedLength is totalLength

        _createOvertimePreparedJobTimer() if preparedLength isnt 0 and not vm.overtimePreparedJob

        type = exportJob.name.replace(/_/g, '-')
        $rootScope.$broadcast 'exportDataPrepared', type, exportJob.params

        $timeout ->
          _addDownloadMessage id, exportJob.name, exportJob.plain if exportJob
        , 500

      $(window).on 'beforeunload', ->
        # Record time when save on localStorage
        if angular.isArray(vm.exportJobs) and vm.exportJobs.length > 0
          timeStamp = moment().valueOf()
          angular.forEach vm.exportJobs, (item) ->
            item.savedTime = timeStamp
          jobs = angular.copy vm.exportJobs
          localStorageService.setItem config.keys.exportJobs, jobs if jobs
      vm
  ]
