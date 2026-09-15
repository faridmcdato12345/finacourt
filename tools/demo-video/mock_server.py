#!/usr/bin/env python3

import argparse
import functools
import http.server
import ssl


class QuietHandler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, _format: str, *_args: object) -> None:
        return


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--directory", required=True)
    parser.add_argument("--cert", required=True)
    parser.add_argument("--key", required=True)
    parser.add_argument("--port", type=int, default=8443)
    args = parser.parse_args()

    handler = functools.partial(QuietHandler, directory=args.directory)
    server = http.server.ThreadingHTTPServer(("0.0.0.0", args.port), handler)
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.load_cert_chain(args.cert, args.key)
    server.socket = context.wrap_socket(server.socket, server_side=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
