
<div id="image-upload-container">
  <h3>
    <span>Did you complete this wikiHow?</span>
  </h3>
  <div id="img_upload" class="article_inner" style="margin-left: 40px; margin-right: 30px;">
    <b>Share an image!</b> We'd love to see your final product.<br/><br/>
    <div id="image-upload-area">
      <form action="<?= $submitUrl ?>"
            method="POST"
            enctype="multipart/form-data"
            id="image-upload-form"
            onsubmit="return AIM.submit(this,
                        {
                          'onStart': jQuery.proxy(imageUploadHandler.uploadOnStart,
                                                  imageUploadHandler),
                          'onComplete': jQuery.proxy(imageUploadHandler.uploadOnComplete,
                                                     imageUploadHandler)
                        })">
        <div id="image-upload-input-wrapper">
          <div id="image-upload-input" class="tip_expander">
            <button class="button tip_expander drop-heading-expander"
                    type="submit"
                    id="image-upload-button"></button>
            <div class="tip_expander" id="image-upload-button-div">
              <p id="image-upload-button-p">Upload image</p>
            </div>
            <input type="file"
                   name="wpUploadImage"
                   onchange="jQuery('#image-upload-input-button').prop('disabled', false);
                             jQuery('#image-upload-form').submit();"
                   id="image-upload-file">
          </div>
          <div id="image-upload-disclaimer" style="color: #888888; font-size: .75em;">
            <br/>
            By uploading this image, I am certifying that I made it myself and that I'm willing to share
            it under wikiHow's <a href="/wikiHow:Terms-of-Use" rel="nofollow">Terms of Use</a>.
          </div>
        </div>
        <div>
          <img src="<?= $loadingWheel ?>"
               id="image-upload-wheel"
               height="50"
               width="50"
               alt="Uploading..."
               class="upload-wheel"
               style="display: none;"/>
        </div>
        <div id="image-upload-error" style="color: #a84810; display: none;">
          &nbsp;
        </div>
        <div id="image-upload-success" style="color: #48a810; display: none;">
          &nbsp;
        </div>
      </form>
      <div id="image-upload-thumb-area" style="display: none;">
        <br/>
        <img src="<?= $loadingWheel ?>"
             id="image-upload-thumb-wheel"
             height="50"
             width="50"
             alt="Loading thumbnail..."
             class="upload-wheel"
             style="display: none;"/>
        <img src="<?= $loadingWheel ?>"
             id="image-upload-thumb"
             style="display: none;"/>
        <br/>
        <a href="#" id="image-upload-delete">Delete</a>
        <div id="image-upload-delete-confirm" style="display: none;">
          Are you sure?
          <pre style="display:inline;">    </pre>
          <a href="#" id="image-upload-delete-confirm-yes">Yes</a>
          <pre style="display:inline;">    </pre>
          /
          <pre style="display:inline;">    </pre>
          <a href="#" id="image-upload-delete-confirm-no">No</a>
        </div>
      </div>
      <div id="image-upload-delete-error" style="color: #a84810; display: none;">
        &nbsp;
      </div>
      <div id="image-upload-delete-success" style="display: none;">
        &nbsp;
      </div>
    </div>
    <br/>
  </div>
</div>
