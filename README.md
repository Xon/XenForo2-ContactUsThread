# Contact Us - Thread

When the Contact Us form is used, this addon will create a thread in a specified forum.

Associates the thread ownership with the logged in user or as a guest user, and uses a phrase to format the message contents.

The username for guest users must pass the configured username requirements.

Phrases (used depending on if the user is logged in or not):
- ContactUs_Message_User
- ContactUs_Message_Guest

Variables sent to the phrase:
- username
- subject
- message
- email
- ip
- spam_trigger_logs

Allows enforcing flood timer on the contact-us form, even for guests.