AttestationTool
===============

version 2015.03.17

The Attestation Tool is a multi purpose GUI used in the production of computational lexica and gold standard data for NE tagging. It addresses the situation of having a comprehensive dictionary consisting of headwords (lemmata) and corresponding quotations in which occurrences of the headwords were automatically matched. This tool is meant to manually evaluate and correct those occurrences of the headwords, especially in cases where occurrences were faultily or yet partially matched. It can also be used to manually correct automatically executed tagging of Named Entities in texts.

In terms of workflow, working with the Attestation Tool will mean:

[1] Loading either dictionary data or text data data into the tool, 
[2] Using the tool to process the data, 
[3] Finally exporting the result of your work in the same format as it came in.

Points 1 and 3 are about importing and exporting data, so working with the Attestation Tool is essentially point number 2. The tool has the form of a web application that can be run from any computer in a local network. It allows several users to work and deliver their input from different computers at the same time. The tool has been built for speed. It presents information to the users in such a way that quick evaluation is possible. When user actions are needed, especially the frequent ones, those can be performed from the keyboard, since this allows for faster responses than clicking the mouse on screen buttons. This way, when the automatic matching has worked out reasonably well, users can very easily scan through the results, quickly correct some mishaps and hit the spacebar to get the next lemma.