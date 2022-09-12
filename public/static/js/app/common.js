var Common = {
    ext2Mime: function (ext) {
    var mime = {
        opus: 'video/ogg',
        ogv: 'video/ogg',
        mp4: 'video/mp4',
        m4v: 'video/mp4',
        mkv: 'video/x-matroska',
        m4a: 'audio/mp4',
        mp3: 'audio/mpeg',
        aac: 'audio/aac',
        oga: 'audio/ogg',
        m3u8: 'application/x-mpegURL',
        jpg: 'image/jpeg',
        jpeg: 'image/jpeg',
        gif: 'image/gif',
        png: 'image/png',
        svg: 'image/svg+xml',
        webp: 'image/webp'
    }
    return mime[ext];
}
}