/*
 * @deprecated
 * Compatibility shim for browsers that already registered /firebase-messaging-sw.js.
 * Do not register this file from application code.
 * New installs use /sw.js with the public Firebase web config in the query string.
 */

/* eslint-disable no-undef */
importScripts('/sw.js');
