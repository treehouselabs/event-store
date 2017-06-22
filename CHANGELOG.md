# Changelog

All Notable changes to `event-store` will be documented in this file

# 1.3
* Made event store table name configurable in `DbalEventStore`

# 1.2.1
* Fixed bug in `SimpleUpcasterChain` that would break the chain when a registered upcaster did not support a given event.

# 1.2

* Added support for upcasting to multiple events
* Deprecated support of upcasters returning non array values

# 1.1.1

Fixed partial stream upcasting 

# 1.1

Added partial stream support

# 1.0 

First release
