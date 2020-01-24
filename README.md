# Grobid Plugin
Plugin that integrates Grobid functionality with OJS 3.1+ and provides conversion of scientific articles from PDF to JATS XML.

[Grobid](https://grobid.readthedocs.io/en/latest/) is a machine learning tool for parsing and semantic labeling of scientific documents developed by Patrice Lopez. The advantage of ML approach is that it doesn't require any prior tagging of a document; with enough annotated training data Grobid is able to parse raw text from PDFs and label them with good accuracy. Main [Grobid repository](https://github.com/kermitt2/grobid); testing instance of Grobid modified to perform PDF to JATS XML conversion is available [here](https://grobid.e-medjournal.com/).  
## Installation
1. `git clone https://github.com/Vitaliy-1/grobidPlugin.git grobid`.
2. In plugin settings specify Grobid Service host, including port if different from 80.
