# Event Sourced Chess - Example App

Chess is an interesting domain that is small enough but complex enough to use it as a nice example for the event sourcing library and in an example project to experiment with it.

There is a domain specific language, [algebraic notation](https://en.wikipedia.org/wiki/Algebraic_notation_(chess)) in the context of chess.

To implement event sourcing, this example is using the Phauthentic event sourcing library.

* [Phauthentic Event Sourcing](https://github.com/Phauthentic/event-sourcing)
* [Phauthentic Event Store](https://github.com/Phauthentic/event-store)
* [Phauthentic Snapshot Store](https://github.com/Phauthentic/snapshot-sourcing)
* [Phauthentic Correlation ID Bundle](https://github.com/Phauthentic/correlation-id-symfony-bundle)
* [Phauthentic Error Response](https://github.com/Phauthentic/error-response)

The goals of this repository are:

* Providing an example of the event sourcing library.
* Experimenting with the implementation in a real app.

## Installation

```sh
composer require phauthentic/event-sourcing-chess-example
docker compose up
bin/console serve
```

## Ubiquitous Language

| Term               | Description                                      |
|--------------------|--------------------------------------------------|
| Algebraic Notation | Standard system for recording chess moves using coordinates (e.g., e4, Nf3) |
| Check             | A threat to capture the opponent's king         |
| Checkmate         | When a player's king is in check and there is no legal move to escape |
| Castling          | Special move involving the king and a rook       |
| En Passant        | Special pawn capture move                        |
| Promotion         | Advancing a pawn to the eighth rank to become a queen, rook, bishop, or knight |
| Stalemate         | A situation where a player has no legal moves and their king is not in check |
| Zugzwang          | A position in which any move will weaken a player's position |

## License

GPLv3 - Copyright Florian Krämer