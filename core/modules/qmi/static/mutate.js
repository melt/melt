function qmi_mutate(action_blobs) {
    if (typeof(action_blobs) != "object")
        action_blobs = [action_blobs];
    var identity = null;
    var operation_blobs = [];
    $.each(action_blobs, function(i, action_blob) {
        var instance_id = null;
        if (typeof(action_blob) == "object") {
            instance_id = action_blob[1];
            action_blob = action_blob[0];
        }
        var match = action_blob.match(/([^,]+),(.*)/);
        if (match === null) {
            console.error("One or more action blobs passed is invalid.");
            return false;
        }
        if (identity === null) {
            identity = match[1];
        } else if (identity != match[1]) {
            console.error("Multiple interfaces cannot be mutated in the same request!");
            return false;
        }
        operation_blobs.push(match[2]);
        if (instance_id != null)
            operation_blobs.push("@" + instance_id);
    });
    if (operation_blobs.length === 0)
        return null;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", QMI_MUTATE_URL, false);
    var qmi_e = document.getElementById(identity);
    if (qmi_e == null) {
        console.error("Qmi mutation failed! Could not locate hidden qmi blob element: #" + identity);
        return null;
    }
    xhr.send(qmi_e.value + "," + operation_blobs.join(","));
    var qmi_blob_length = xhr.getResponseHeader("X-Qmi-Blob-Length");
    if (qmi_blob_length == null)
        return null;
    if (xhr.status == 404) {
        // QMI state no longer valid, reload page.
        if (confirm(QMI_ERROR_STATE_INVALID_CONFIRM)) {
            window.location.reload();
        }
        return {response: null, new_instance_id: null};;
    }
    qmi_blob_length = parseInt(qmi_blob_length, 10);
    qmi_e.value = xhr.responseText.substring(0, qmi_blob_length);
    var response = eval("(" + xhr.responseText.substring(qmi_blob_length) + ")");
    var new_instance_id = xhr.getResponseHeader('X-Qmi-Instance-Id');
    return {response: response, new_instance_id: new_instance_id};
}